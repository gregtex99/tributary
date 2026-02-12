<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\ActionItem;
use App\Models\Signal;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): DashboardResource
    {
        $itemsByState = ActionItem::query()
            ->select('current_state', DB::raw('COUNT(*) as count'))
            ->groupBy('current_state')
            ->pluck('count', 'current_state')
            ->toArray();

        $pendingNudges = ActionItem::query()->needsNudge()->count();

        $signalsToday = Signal::query()
            ->where('detected_at', '>=', now()->startOfDay())
            ->count();

        $avgResolutionSeconds = (float) (ActionItem::query()
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))) as avg_seconds')
            ->value('avg_seconds') ?? 0);

        return new DashboardResource([
            'items_by_state' => $itemsByState,
            'pending_nudges' => $pendingNudges,
            'signals_today' => $signalsToday,
            'avg_resolution_seconds' => round($avgResolutionSeconds, 2),
        ]);
    }
}
