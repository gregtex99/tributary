<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SignalResource;
use App\Models\ActionItem;
use App\Models\Signal;
use App\Services\ActionItemStateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignalController extends Controller
{
    public function __construct(private readonly ActionItemStateManager $stateManager) {}

    public function index(Request $request)
    {
        $query = Signal::query();

        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }

        if ($request->filled('signal_type')) {
            $query->where('signal_type', $request->string('signal_type')->toString());
        }

        $signals = $query
            ->latest('detected_at')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 50))));

        return SignalResource::collection($signals);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'source_ref' => ['nullable', 'string', 'max:255'],
            'signal_type' => ['required', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'detected_at' => ['nullable', 'date'],
            'payload' => ['nullable', 'array'],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $signal = Signal::create([
                'org_id' => $validated['org_id'],
                'source' => $validated['source'],
                'source_ref' => $validated['source_ref'] ?? null,
                'signal_type' => $validated['signal_type'],
                'actor' => $validated['actor'] ?? 'system',
                'detected_at' => $validated['detected_at'] ?? now(),
                'payload' => $validated['payload'] ?? null,
            ]);

            $matchedItem = null;
            $transition = null;

            if (! empty($signal->source_ref)) {
                $matchedItem = ActionItem::query()
                    ->active()
                    ->where('org_id', $signal->org_id)
                    ->where('source_ref', $signal->source_ref)
                    ->first();
            }

            if ($matchedItem !== null) {
                $signal->matched_item_id = $matchedItem->id;
                $signal->save();

                $nextState = $this->resolveSignalTransition($signal->signal_type, $matchedItem->current_state);

                if ($nextState !== null) {
                    $transition = $this->stateManager->applyTransition(
                        $matchedItem,
                        $nextState,
                        'signal:'.$signal->signal_type,
                        $signal,
                        'Auto-transition from signal intake'
                    );
                } else {
                    $this->stateManager->refreshNudgeSchedule($matchedItem);
                }
            }

            return [
                'signal' => $signal->fresh('actionItem'),
                'matched_item_id' => $matchedItem?->id,
                'transitioned' => $transition !== null,
                'to_state' => $transition?->to_state,
            ];
        });

        return (new SignalResource($result['signal']))
            ->additional([
                'meta' => [
                    'matched_item_id' => $result['matched_item_id'],
                    'transitioned' => $result['transitioned'],
                    'to_state' => $result['to_state'],
                ],
            ])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    private function resolveSignalTransition(string $signalType, string $currentState): ?string
    {
        return match ($signalType) {
            'email_sent' => $currentState === 'action_needed' ? 'waiting_on_reply' : null,
            'email_received' => $currentState === 'waiting_on_reply' ? 'action_needed' : null,
            'calendar_invite' => in_array($currentState, ['action_needed', 'waiting_on_reply'], true)
                ? 'waiting_on_calendar'
                : null,
            'calendar_accepted' => $currentState === 'waiting_on_calendar' ? 'done' : null,
            default => null,
        };
    }
}
