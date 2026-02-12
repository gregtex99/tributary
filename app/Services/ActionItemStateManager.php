<?php

namespace App\Services;

use App\Models\ActionItem;
use App\Models\ActionItemTransition;
use App\Models\NudgeRule;
use App\Models\Signal;

class ActionItemStateManager
{
    public function applyTransition(
        ActionItem $item,
        string $toState,
        string $trigger,
        ?Signal $signal = null,
        ?string $notes = null,
    ): ?ActionItemTransition {
        $fromState = $item->current_state;

        if ($fromState === $toState) {
            $this->refreshNudgeSchedule($item);

            return null;
        }

        $item->current_state = $toState;

        if (in_array($toState, ['done', 'cancelled'], true)) {
            $item->resolved_at = now();
        } elseif ($item->resolved_at !== null) {
            $item->resolved_at = null;
        }

        $item->save();
        $this->refreshNudgeSchedule($item);

        return ActionItemTransition::create([
            'action_item_id' => $item->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'trigger' => $trigger,
            'signal_data' => $signal?->payload,
            'notes' => $notes,
        ]);
    }

    public function refreshNudgeSchedule(ActionItem $item): void
    {
        if (in_array($item->current_state, ['done', 'cancelled'], true)) {
            $item->next_nudge_at = null;
            $item->save();

            return;
        }

        $rule = $this->resolveRule($item);

        if ($rule !== null) {
            $item->max_nudges = $rule->max_nudges;
        }

        if ($item->nudge_count >= $item->max_nudges) {
            $item->next_nudge_at = null;
            $item->save();

            return;
        }

        $hours = $item->nudge_count > 0
            ? ($rule?->repeat_nudge_hours ?? $item->nudge_after_hours)
            : ($rule?->first_nudge_hours ?? $item->nudge_after_hours);

        $item->next_nudge_at = now()->addHours($hours);
        $item->save();
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
            ->orderByRaw("CASE WHEN item_type = ? THEN 0 ELSE 1 END", [$item->source])
            ->first();
    }
}
