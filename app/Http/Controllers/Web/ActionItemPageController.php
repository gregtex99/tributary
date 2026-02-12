<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Services\ActionItemStateManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActionItemPageController extends Controller
{
    public function __construct(private readonly ActionItemStateManager $stateManager) {}

    public function index(Request $request): Response
    {
        $filters = [
            'state' => $request->string('state')->toString(),
            'source' => $request->string('source')->toString(),
            'ball_with' => $request->string('ball_with')->toString(),
            'sort' => $request->string('sort')->toString() ?: 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        ];

        $query = ActionItem::query();

        if ($filters['state'] !== '') {
            if ($filters['state'] === 'active') {
                $query->active();
            } else {
                $query->where('current_state', $filters['state']);
            }
        }

        if ($filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        if ($filters['ball_with'] !== '') {
            $query->where('ball_with', $filters['ball_with']);
        }

        $sortableColumns = ['created_at', 'next_nudge_at'];
        $sortBy = in_array($filters['sort'], $sortableColumns, true) ? $filters['sort'] : 'created_at';

        $items = $query
            ->orderBy($sortBy, $filters['direction'])
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $filterOptions = [
            'states' => ActionItem::query()->distinct()->orderBy('current_state')->pluck('current_state')->values(),
            'sources' => ActionItem::query()->distinct()->orderBy('source')->pluck('source')->values(),
            'ball_with' => ActionItem::query()->distinct()->orderBy('ball_with')->pluck('ball_with')->values(),
        ];

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
        ]);
    }

    public function show(ActionItem $item): Response
    {
        $item->load([
            'transitions' => fn ($query) => $query->oldest(),
            'nudges' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('Items/Show', [
            'item' => $item,
            'stateOptions' => [
                'action_needed',
                'waiting_on_reply',
                'waiting_on_calendar',
                'done',
                'cancelled',
            ],
        ]);
    }

    public function update(Request $request, ActionItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ball_with' => ['required', 'string', 'max:255'],
            'waiting_for' => ['nullable', 'string', 'max:255'],
            'nudge_after_hours' => ['required', 'integer', 'min:1'],
            'max_nudges' => ['required', 'integer', 'min:1'],
        ]);

        $item->fill($validated);
        $item->save();

        $this->stateManager->refreshNudgeSchedule($item);

        return back()->with('success', 'Action item updated.');
    }

    public function transition(Request $request, ActionItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'to_state' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->stateManager->applyTransition(
            $item,
            $validated['to_state'],
            'manual_dashboard',
            null,
            $validated['notes'] ?? 'Manual state change from dashboard'
        );

        return back()->with('success', 'State updated.');
    }
}
