<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActionItem;
use App\Models\NudgeLog;
use App\Models\Signal;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function __invoke(Request $request)
    {
        // Simple token-based access control
        if ($request->query('token') !== config('app.monitor_token')) {
            abort(403, 'Invalid monitor token');
        }

        $activeItems = ActionItem::query()
            ->active()
            ->orderByRaw("CASE current_state WHEN 'action_needed' THEN 1 WHEN 'waiting_response' THEN 2 WHEN 'on_hold' THEN 3 ELSE 4 END")
            ->orderBy('updated_at', 'desc')
            ->get();

        $stats = [
            'total_active' => $activeItems->count(),
            'waiting_greg' => ActionItem::query()->active()->where('ball_with', 'greg')->count(),
            'waiting_others' => ActionItem::query()->active()->where('ball_with', '!=', 'greg')->count(),
            'resolved_7d' => ActionItem::query()
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->subDays(7))
                ->count(),
        ];

        $signals = Signal::query()
            ->with('actionItem:id,title')
            ->orderBy('detected_at', 'desc')
            ->limit(50)
            ->get();

        $nudges = NudgeLog::query()
            ->with('actionItem:id,title')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        return view('monitor', compact('activeItems', 'stats', 'signals', 'nudges'));
    }
}
