<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActionItemResource;
use App\Http\Resources\NudgeLogResource;
use App\Models\ActionItem;
use App\Models\NudgeLog;
use App\Services\ActionItemStateManager;
use Illuminate\Http\Request;

class NudgeController extends Controller
{
    public function __construct(private readonly ActionItemStateManager $stateManager) {}

    public function pending(Request $request)
    {
        $items = ActionItem::query()
            ->needsNudge()
            ->orderBy('next_nudge_at')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 50))));

        return ActionItemResource::collection($items);
    }

    public function draft(Request $request, string $itemId)
    {
        $item = ActionItem::query()->findOrFail($itemId);

        if (in_array($item->current_state, ['done', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Cannot draft nudges for completed or cancelled items.',
            ], 422);
        }

        $channel = $request->string('channel')->toString() ?: 'email';
        $nudgeNumber = $item->nudge_count + 1;
        $messageText = $this->buildDraftMessage($item, $nudgeNumber);

        $nudge = NudgeLog::create([
            'action_item_id' => $item->id,
            'nudge_number' => $nudgeNumber,
            'channel' => $channel,
            'message_text' => $messageText,
        ]);

        return (new NudgeLogResource($nudge))
            ->additional(['meta' => ['suggested_message' => $messageText]]);
    }

    public function approve(Request $request, string $itemId): NudgeLogResource
    {
        $item = ActionItem::query()->findOrFail($itemId);

        $nudge = $this->resolveNudgeLog($item, $request);

        if ($nudge === null) {
            abort(422, 'No nudge draft found to approve.');
        }

        if ($nudge->approved_at === null) {
            $nudge->approved_at = now();
            $nudge->save();
        }

        return new NudgeLogResource($nudge);
    }

    public function sent(Request $request, string $itemId): NudgeLogResource
    {
        $item = ActionItem::query()->findOrFail($itemId);

        $nudge = $this->resolveNudgeLog($item, $request, true);

        if ($nudge === null) {
            abort(422, 'No approved nudge found to mark as sent.');
        }

        if ($nudge->sent_at === null) {
            $nudge->sent_at = now();
            $nudge->save();

            $item->nudge_count = max($item->nudge_count, $nudge->nudge_number);
            $item->save();

            $this->stateManager->refreshNudgeSchedule($item);
        }

        return new NudgeLogResource($nudge);
    }

    private function resolveNudgeLog(ActionItem $item, Request $request, bool $requiresApproval = false): ?NudgeLog
    {
        if ($request->filled('nudge_log_id')) {
            $query = NudgeLog::query()
                ->where('action_item_id', $item->id)
                ->where('id', $request->string('nudge_log_id')->toString());

            if ($requiresApproval) {
                $query->whereNotNull('approved_at');
            }

            return $query->first();
        }

        $query = NudgeLog::query()
            ->where('action_item_id', $item->id)
            ->whereNull('sent_at')
            ->latest();

        if ($requiresApproval) {
            $query->whereNotNull('approved_at');
        }

        return $query->first();
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
