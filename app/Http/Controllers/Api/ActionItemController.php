<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActionItemResource;
use App\Http\Resources\ActionItemTransitionResource;
use App\Models\ActionItem;
use App\Services\ActionItemStateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionItemController extends Controller
{
    public function __construct(private readonly ActionItemStateManager $stateManager) {}

    public function index(Request $request)
    {
        $query = ActionItem::query();

        if ($request->filled('state')) {
            if ($request->string('state')->toString() === 'active') {
                $query->active();
            } else {
                $query->where('current_state', $request->string('state')->toString());
            }
        }

        if ($request->filled('ball_with')) {
            $query->where('ball_with', $request->string('ball_with')->toString());
        }

        if ($request->filled('source')) {
            $query->where('source', $request->string('source')->toString());
        }

        $items = $query
            ->latest()
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return ActionItemResource::collection($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'source_ref' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'current_state' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'ball_with' => ['nullable', 'string', 'max:255'],
            'waiting_for' => ['nullable', 'string', 'max:255'],
            'nudge_after_hours' => ['nullable', 'integer', 'min:1'],
            'nudge_count' => ['nullable', 'integer', 'min:0'],
            'max_nudges' => ['nullable', 'integer', 'min:1'],
            'resolved_at' => ['nullable', 'date'],
        ]);

        $validated['current_state'] = $validated['current_state'] ?? $validated['state'] ?? 'action_needed';
        unset($validated['state']);
        $validated['ball_with'] = $validated['ball_with'] ?? $validated['user_id'];

        $item = ActionItem::create($validated);
        $this->stateManager->refreshNudgeSchedule($item);

        return (new ActionItemResource($item->fresh()))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(string $id): ActionItemResource
    {
        $item = ActionItem::query()
            ->with(['transitions' => fn ($q) => $q->latest(), 'nudges' => fn ($q) => $q->latest()])
            ->findOrFail($id);

        return new ActionItemResource($item);
    }

    public function update(Request $request, string $id): ActionItemResource
    {
        $item = ActionItem::query()->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'current_state' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'max:255'],
            'ball_with' => ['sometimes', 'string', 'max:255'],
            'waiting_for' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nudge_after_hours' => ['sometimes', 'integer', 'min:1'],
            'nudge_count' => ['sometimes', 'integer', 'min:0'],
            'max_nudges' => ['sometimes', 'integer', 'min:1'],
            'resolved_at' => ['sometimes', 'nullable', 'date'],
            'transition_notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $newState = $validated['current_state'] ?? $validated['state'] ?? null;
        unset($validated['current_state'], $validated['state']);
        $transitionNotes = $validated['transition_notes'] ?? null;
        unset($validated['transition_notes']);

        if ($validated !== []) {
            $item->fill($validated);
            $item->save();
        }

        if ($newState !== null) {
            $this->stateManager->applyTransition($item, $newState, 'manual_update', null, $transitionNotes);
        } else {
            $this->stateManager->refreshNudgeSchedule($item);
        }

        return new ActionItemResource($item->fresh(['transitions', 'nudges']));
    }

    public function destroy(Request $request, string $id): ActionItemResource
    {
        $item = ActionItem::query()->findOrFail($id);

        $this->stateManager->applyTransition(
            $item,
            'cancelled',
            'manual_cancel',
            null,
            $request->string('notes')->toString() ?: 'Item cancelled via API endpoint.'
        );

        return new ActionItemResource($item->fresh(['transitions', 'nudges']));
    }

    public function transitions(string $id)
    {
        $item = ActionItem::query()->findOrFail($id);

        $transitions = $item->transitions()
            ->latest()
            ->paginate(100);

        return ActionItemTransitionResource::collection($transitions);
    }
}
