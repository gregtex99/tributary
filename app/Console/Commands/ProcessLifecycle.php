<?php

namespace App\Console\Commands;

use App\Models\ActionItem;
use App\Models\NudgeLog;
use App\Models\NudgeRule;
use App\Models\Signal;
use App\Services\ActionItemStateManager;
use App\Services\NudgeDraftGenerator;
use Illuminate\Console\Command;

class ProcessLifecycle extends Command
{
    protected $signature = 'tributary:process {--org=}';

    protected $description = 'Process action-item lifecycle nudges, stale auto-closures, and calendar resolution.';

    public function __construct(
        private readonly ActionItemStateManager $stateManager,
        private readonly NudgeDraftGenerator $draftGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $orgId = $this->option('org');

        $this->info('Starting lifecycle processing...');

        [$nudged, $escalated] = $this->processPendingNudges($orgId);
        $calendarResolved = $this->resolveCalendarItems($orgId);
        [$autoCancelled, $doneSkipped] = $this->autoCloseStaleItems($orgId);

        $this->newLine();
        $this->info('Lifecycle processing complete.');
        $this->line("- Nudges drafted: {$nudged}");
        $this->line("- Escalations created: {$escalated}");
        $this->line("- Calendar items resolved: {$calendarResolved}");
        $this->line("- Auto-cancelled stale waiting items: {$autoCancelled}");
        $this->line("- Done items skipped (>7 days): {$doneSkipped}");

        return self::SUCCESS;
    }

    /**
     * @return array{int,int}
     */
    private function processPendingNudges(?string $orgId): array
    {
        $nudged = 0;
        $escalated = 0;

        $query = ActionItem::query()
            ->active()
            ->whereNotNull('next_nudge_at')
            ->where('next_nudge_at', '<=', now())
            ->orderBy('next_nudge_at');

        if ($orgId !== null) {
            $query->where('org_id', $orgId);
        }

        foreach ($query->cursor() as $item) {
            $rule = $this->resolveRule($item);
            $maxNudges = $rule?->max_nudges ?? $item->max_nudges;

            if ($item->max_nudges !== $maxNudges) {
                $item->max_nudges = $maxNudges;
            }

            if ($item->nudge_count < $maxNudges) {
                $nudgeNumber = $item->nudge_count + 1;

                NudgeLog::create([
                    'action_item_id' => $item->id,
                    'nudge_number' => $nudgeNumber,
                    'channel' => 'discord',
                    'message_text' => $this->draftGenerator->generateNudge($item),
                ]);

                $item->nudge_count = $nudgeNumber;
                $item->next_nudge_at = now()->addHours($rule?->repeat_nudge_hours ?? $item->nudge_after_hours);
                $item->save();
                $nudged++;

                continue;
            }

            NudgeLog::create([
                'action_item_id' => $item->id,
                'nudge_number' => $item->nudge_count,
                'channel' => 'discord',
                'message_text' => $this->draftGenerator->generateEscalation($item, $item->nudge_count),
            ]);

            $item->ball_with = 'greg';
            $item->next_nudge_at = null;
            $item->save();
            $escalated++;
        }

        return [$nudged, $escalated];
    }

    private function resolveCalendarItems(?string $orgId): int
    {
        $resolved = 0;

        $query = ActionItem::query()
            ->active()
            ->where('current_state', 'waiting_on_calendar');

        if ($orgId !== null) {
            $query->where('org_id', $orgId);
        }

        foreach ($query->cursor() as $item) {
            $signal = Signal::query()
                ->where('signal_type', 'calendar_accepted')
                ->where(function ($signalQuery) use ($item): void {
                    $signalQuery->where('matched_item_id', $item->id);

                    if ($item->source_ref !== null) {
                        $signalQuery->orWhere(function ($nested) use ($item): void {
                            $nested->where('org_id', $item->org_id)
                                ->where('source_ref', $item->source_ref);
                        });
                    }
                })
                ->latest('detected_at')
                ->first();

            if ($signal === null) {
                continue;
            }

            $transition = $this->stateManager->applyTransition(
                $item,
                'done',
                'lifecycle:calendar_accepted',
                $signal,
                'Auto-resolved by lifecycle processor from calendar_accepted signal.'
            );

            if ($transition !== null) {
                $resolved++;
            }
        }

        return $resolved;
    }

    /**
     * @return array{int,int}
     */
    private function autoCloseStaleItems(?string $orgId): array
    {
        $doneSkippedQuery = ActionItem::query()
            ->where('current_state', 'done')
            ->where(function ($query): void {
                $query
                    ->where('resolved_at', '<=', now()->subDays(7))
                    ->orWhere(function ($fallback): void {
                        $fallback->whereNull('resolved_at')
                            ->where('updated_at', '<=', now()->subDays(7));
                    });
            });

        if ($orgId !== null) {
            $doneSkippedQuery->where('org_id', $orgId);
        }

        $doneSkipped = $doneSkippedQuery->count();
        $autoCancelled = 0;

        $waitingQuery = ActionItem::query()
            ->active()
            ->where('current_state', 'like', 'waiting_on_%')
            ->where('updated_at', '<=', now()->subDays(30));

        if ($orgId !== null) {
            $waitingQuery->where('org_id', $orgId);
        }

        foreach ($waitingQuery->cursor() as $item) {
            $rule = $this->resolveRule($item);
            $maxNudges = $rule?->max_nudges ?? $item->max_nudges;

            if ($item->nudge_count < $maxNudges) {
                continue;
            }

            $transition = $this->stateManager->applyTransition(
                $item,
                'cancelled',
                'lifecycle:auto_cancel_stale',
                null,
                'Auto-cancelled: no response after 30 days'
            );

            if ($transition !== null) {
                $autoCancelled++;
            }
        }

        return [$autoCancelled, $doneSkipped];
    }

    private function resolveRule(ActionItem $item): ?NudgeRule
    {
        $rule = NudgeRule::query()
            ->where('org_id', $item->org_id)
            ->where('item_type', $item->source)
            ->first();

        if ($rule !== null) {
            return $rule;
        }

        $fallback = NudgeRule::query()
            ->where('org_id', $item->org_id)
            ->where('item_type', 'default')
            ->first();

        if ($fallback !== null) {
            return $fallback;
        }

        return NudgeRule::query()
            ->where('org_id', 'default')
            ->whereIn('item_type', [$item->source, 'default'])
            ->orderByRaw('CASE WHEN item_type = ? THEN 0 ELSE 1 END', [$item->source])
            ->first();
    }
}
