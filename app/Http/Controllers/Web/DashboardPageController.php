<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\ActionItemTransition;
use App\Models\Signal;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPageController extends Controller
{
    public function __invoke(): Response
    {
        $activeItems = ActionItem::query()->active()->count();
        $pendingNudges = ActionItem::query()->needsNudge()->count();
        $signalsToday = Signal::query()->where('detected_at', '>=', now()->startOfDay())->count();

        $avgResolutionSeconds = (float) (ActionItem::query()
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at'])
            ->avg(fn (ActionItem $item) => (float) ($item->resolved_at?->diffInSeconds($item->created_at) ?? 0)) ?? 0);

        $recentTransitions = ActionItemTransition::query()
            ->with('actionItem:id,title,current_state')
            ->latest()
            ->limit(10)
            ->get();

        $attentionItems = ActionItem::query()
            ->where('current_state', 'action_needed')
            ->where('ball_with', 'greg')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_items' => $activeItems,
                'pending_nudges' => $pendingNudges,
                'signals_today' => $signalsToday,
                'avg_resolution_seconds' => round($avgResolutionSeconds, 2),
            ],
            'recentTransitions' => $recentTransitions,
            'attentionItems' => $attentionItems,
        ]);
    }
}
