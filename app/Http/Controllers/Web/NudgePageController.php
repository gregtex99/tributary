<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\NudgeLog;
use App\Services\ActionItemStateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NudgePageController extends Controller
{
    public function __construct(private readonly ActionItemStateManager $stateManager) {}

    public function index(): Response
    {
        $pendingDrafts = NudgeLog::query()
            ->whereNull('approved_at')
            ->whereNull('sent_at')
            ->with('actionItem')
            ->latest()
            ->get();

        $draftItemIds = $pendingDrafts
            ->pluck('action_item_id')
            ->filter()
            ->values();

        $needsDraft = ActionItem::query()
            ->needsNudge()
            ->when($draftItemIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $draftItemIds))
            ->orderBy('next_nudge_at')
            ->get();

        $queue = $pendingDrafts
            ->map(fn (NudgeLog $draft) => [
                'item' => $draft->actionItem,
                'draft' => $draft,
            ])
            ->values()
            ->all();

        foreach ($needsDraft as $item) {
            $queue[] = [
                'item' => $item,
                'draft' => null,
            ];
        }

        $sentHistory = NudgeLog::query()
            ->whereNotNull('sent_at')
            ->with('actionItem:id,title,current_state,ball_with')
            ->latest('sent_at')
            ->limit(30)
            ->get();

        return Inertia::render('Nudges/Index', [
            'queue' => $queue,
            'sentHistory' => $sentHistory,
        ]);
    }

    public function draft(ActionItem $item): RedirectResponse
    {
        if (in_array($item->current_state, ['done', 'cancelled'], true)) {
            return back()->with('error', 'Cannot draft nudges for completed items.');
        }

        $existingDraft = NudgeLog::query()
            ->where('action_item_id', $item->id)
            ->whereNull('approved_at')
            ->whereNull('sent_at')
            ->latest()
            ->first();

        if ($existingDraft !== null) {
            return back()->with('success', 'Draft already exists for this item.');
        }

        $nudgeNumber = $item->nudge_count + 1;

        NudgeLog::create([
            'action_item_id' => $item->id,
            'nudge_number' => $nudgeNumber,
            'channel' => 'email',
            'message_text' => $this->buildDraftMessage($item, $nudgeNumber),
        ]);

        return back()->with('success', 'Draft nudge created.');
    }

    public function updateDraft(Request $request, NudgeLog $nudge): RedirectResponse
    {
        $validated = $request->validate([
            'message_text' => ['required', 'string'],
            'channel' => ['nullable', 'string', 'max:255'],
        ]);

        if ($nudge->approved_at !== null || $nudge->sent_at !== null) {
            return back()->with('error', 'Cannot edit an approved or sent nudge.');
        }

        $nudge->message_text = $validated['message_text'];
        if (! empty($validated['channel'])) {
            $nudge->channel = $validated['channel'];
        }
        $nudge->save();

        return back()->with('success', 'Draft updated.');
    }

    public function approve(NudgeLog $nudge): RedirectResponse
    {
        if ($nudge->approved_at === null) {
            $nudge->approved_at = now();
            $nudge->save();
        }

        return back()->with('success', 'Draft approved.');
    }

    public function skip(NudgeLog $nudge): RedirectResponse
    {
        if ($nudge->approved_at !== null || $nudge->sent_at !== null) {
            return back()->with('error', 'Only unapproved drafts can be skipped.');
        }

        $nudge->delete();

        return back()->with('success', 'Draft skipped.');
    }

    public function sent(NudgeLog $nudge): RedirectResponse
    {
        if ($nudge->approved_at === null) {
            return back()->with('error', 'Approve nudge before marking as sent.');
        }

        if ($nudge->sent_at === null) {
            $nudge->sent_at = now();
            $nudge->save();

            $item = $nudge->actionItem;
            if ($item !== null) {
                $item->nudge_count = max($item->nudge_count, $nudge->nudge_number);
                $item->save();
                $this->stateManager->refreshNudgeSchedule($item);
            }
        }

        return back()->with('success', 'Nudge marked as sent.');
    }

    private function buildDraftMessage(ActionItem $item, int $nudgeNumber): string
    {
        $owner = $item->ball_with ?: 'there';

        return sprintf(
            'Nudge #%d: Hi %s — quick follow-up on "%s". Current state is %s. Let me know if you need anything to move this forward.',
            $nudgeNumber,
            $owner,
            $item->title,
            str_replace('_', ' ', $item->current_state)
        );
    }
}
