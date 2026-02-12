<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tributary — Monitor</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0f12;
            --surface: #161a20;
            --surface2: #1c2128;
            --border: #2a303a;
            --text: #e2e8f0;
            --text-dim: #8b95a5;
            --accent: #6366f1;
            --red: #ef4444;
            --yellow: #eab308;
            --green: #22c55e;
            --gray: #6b7280;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }

        /* Header */
        header {
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
        }
        .header-inner {
            display: flex; align-items: center; justify-content: space-between;
        }
        .logo {
            font-size: 1.25rem; font-weight: 700; letter-spacing: -0.02em;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .logo span { color: var(--accent); }
        .refresh-info {
            font-size: 0.8rem; color: var(--text-dim);
            display: flex; align-items: center; gap: 0.5rem;
        }
        .pulse {
            width: 8px; height: 8px; border-radius: 50%; background: var(--green);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Stats */
        .stats {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
            padding: 1.5rem 0;
        }
        @media (max-width: 640px) { .stats { grid-template-columns: repeat(2, 1fr); } }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 1.25rem;
        }
        .stat-label { font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 2rem; font-weight: 700; margin-top: 0.25rem; }

        /* Main grid */
        .main-grid {
            display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem;
            padding-bottom: 3rem;
        }
        @media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }

        /* Cards */
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
            font-weight: 600; font-size: 0.9rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header .count {
            background: var(--surface2); border-radius: 999px;
            padding: 0.15rem 0.6rem; font-size: 0.75rem; color: var(--text-dim);
        }

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            text-align: left; padding: 0.6rem 1rem; font-size: 0.7rem;
            color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }
        .items-table td {
            padding: 0.75rem 1rem; border-bottom: 1px solid var(--border);
            font-size: 0.85rem; vertical-align: middle;
        }
        .items-table tr:last-child td { border-bottom: none; }
        .items-table tr:hover td { background: var(--surface2); }

        .item-title { font-weight: 500; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 0.35rem;
            padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem;
            font-weight: 500; white-space: nowrap;
        }
        .badge-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .badge-action_needed { background: rgba(239,68,68,0.15); color: var(--red); }
        .badge-action_needed .badge-dot { background: var(--red); }
        .badge-waiting_response { background: rgba(234,179,8,0.15); color: var(--yellow); }
        .badge-waiting_response .badge-dot { background: var(--yellow); }
        .badge-resolved, .badge-done { background: rgba(34,197,94,0.15); color: var(--green); }
        .badge-resolved .badge-dot, .badge-done .badge-dot { background: var(--green); }
        .badge-on_hold, .badge-cancelled { background: rgba(107,114,128,0.15); color: var(--gray); }
        .badge-on_hold .badge-dot, .badge-cancelled .badge-dot { background: var(--gray); }

        /* Signal feed */
        .signal-list { max-height: 520px; overflow-y: auto; }
        .signal-item {
            padding: 0.7rem 1.25rem; border-bottom: 1px solid var(--border);
            font-size: 0.82rem;
        }
        .signal-item:last-child { border-bottom: none; }
        .signal-item:hover { background: var(--surface2); }
        .signal-time { font-size: 0.7rem; color: var(--text-dim); }
        .signal-type {
            display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px;
            background: var(--surface2); font-size: 0.7rem; color: var(--accent);
            font-family: 'SF Mono', Monaco, monospace; margin: 0.15rem 0;
        }
        .signal-actor { color: var(--text-dim); font-size: 0.75rem; }
        .signal-match { color: var(--text-dim); font-size: 0.72rem; font-style: italic; }

        /* Nudge log */
        .nudge-toggle {
            width: 100%; background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 1rem 1.25rem; color: var(--text);
            font-weight: 600; font-size: 0.9rem; cursor: pointer; text-align: left;
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 1.5rem;
        }
        .nudge-toggle:hover { background: var(--surface2); }
        .nudge-content {
            background: var(--surface); border: 1px solid var(--border); border-top: none;
            border-radius: 0 0 10px 10px; overflow: hidden;
            margin-top: -0.5rem;
        }
        .nudge-item {
            padding: 0.7rem 1.25rem; border-bottom: 1px solid var(--border);
            font-size: 0.82rem; display: flex; gap: 1rem; align-items: baseline;
        }
        .nudge-item:last-child { border-bottom: none; }
        .nudge-channel {
            display: inline-block; padding: 0.1rem 0.4rem; border-radius: 4px;
            background: var(--surface2); font-size: 0.7rem; color: var(--yellow);
            font-family: monospace;
        }
        .nudge-status { font-size: 0.7rem; color: var(--text-dim); }

        .empty { text-align: center; padding: 2rem; color: var(--text-dim); font-size: 0.85rem; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body x-data="monitor()" x-init="startPolling()">

<header>
    <div class="container header-inner">
        <div class="logo"><span>◆</span> Tributary</div>
        <div class="refresh-info">
            <div class="pulse"></div>
            <span>Updated <span x-text="lastRefresh"></span></span>
        </div>
    </div>
</header>

<div class="container">
    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Active Items</div>
            <div class="stat-value">{{ $stats['total_active'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Waiting on Greg</div>
            <div class="stat-value" style="color: var(--red)">{{ $stats['waiting_greg'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Waiting on Others</div>
            <div class="stat-value" style="color: var(--yellow)">{{ $stats['waiting_others'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Resolved (7d)</div>
            <div class="stat-value" style="color: var(--green)">{{ $stats['resolved_7d'] }}</div>
        </div>
    </div>

    <!-- Main content -->
    <div class="main-grid">
        <!-- Active Items -->
        <div class="card">
            <div class="card-header">
                Active Items
                <span class="count">{{ $stats['total_active'] }}</span>
            </div>
            @if($activeItems->isEmpty())
                <div class="empty">No active items — all clear! 🎉</div>
            @else
                <div style="overflow-x: auto;">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>State</th>
                                <th>Ball With</th>
                                <th>Waiting Since</th>
                                <th>Days</th>
                                <th>Last Signal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeItems as $item)
                                <tr>
                                    <td class="item-title" title="{{ $item->title }}">{{ $item->title }}</td>
                                    <td>
                                        <span class="badge badge-{{ $item->current_state }}">
                                            <span class="badge-dot"></span>
                                            {{ str_replace('_', ' ', $item->current_state) }}
                                        </span>
                                    </td>
                                    <td>{{ $item->ball_with }}</td>
                                    <td style="color: var(--text-dim); font-size: 0.8rem;">
                                        {{ $item->updated_at->format('M j, g:ia') }}
                                    </td>
                                    <td>
                                        @php $days = (int) $item->updated_at->diffInDays(now()); @endphp
                                        <span style="color: {{ $days > 3 ? 'var(--red)' : ($days > 1 ? 'var(--yellow)' : 'var(--text-dim)') }}">
                                            {{ $days }}d
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: var(--text-dim);">
                                        @php $lastSignal = $item->signals()->latest('detected_at')->first(); @endphp
                                        @if($lastSignal)
                                            <span class="signal-type">{{ $lastSignal->signal_type }}</span>
                                            <br>{{ $lastSignal->detected_at->diffForHumans() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Signal Feed -->
        <div class="card">
            <div class="card-header">
                Signal Feed
                <span class="count">{{ $signals->count() }}</span>
            </div>
            @if($signals->isEmpty())
                <div class="empty">No signals recorded yet</div>
            @else
                <div class="signal-list">
                    @foreach($signals as $signal)
                        <div class="signal-item">
                            <div class="signal-time">{{ $signal->detected_at->format('M j, g:ia') }}</div>
                            <div><span class="signal-type">{{ $signal->signal_type }}</span></div>
                            <div class="signal-actor">{{ $signal->actor }} via {{ $signal->source }}</div>
                            @if($signal->actionItem)
                                <div class="signal-match">→ {{ Str::limit($signal->actionItem->title, 50) }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Nudge Log (collapsible) -->
    <div x-data="{ open: false }" style="margin-bottom: 3rem;">
        <button class="nudge-toggle" @click="open = !open">
            <span>Nudge Log ({{ $nudges->count() }})</span>
            <span x-text="open ? '▲' : '▼'" style="font-size: 0.75rem;"></span>
        </button>
        <div class="nudge-content" x-show="open" x-cloak x-transition>
            @if($nudges->isEmpty())
                <div class="empty">No nudges sent yet</div>
            @else
                @foreach($nudges as $nudge)
                    <div class="nudge-item">
                        <div style="flex-shrink: 0; color: var(--text-dim); font-size: 0.75rem; min-width: 100px;">
                            {{ $nudge->created_at->format('M j, g:ia') }}
                        </div>
                        <div style="flex: 1;">
                            <span class="nudge-channel">{{ $nudge->channel }}</span>
                            @if($nudge->actionItem)
                                <span style="color: var(--text-dim);">→</span>
                                {{ Str::limit($nudge->actionItem->title, 40) }}
                            @endif
                            <span style="font-size: 0.75rem; color: var(--text-dim);">#{{ $nudge->nudge_number }}</span>
                        </div>
                        <div class="nudge-status">
                            @if($nudge->response_detected_at)
                                <span style="color: var(--green);">✓ responded</span>
                            @elseif($nudge->sent_at)
                                <span style="color: var(--yellow);">sent {{ $nudge->sent_at->diffForHumans() }}</span>
                            @elseif($nudge->approved_at)
                                <span style="color: var(--accent);">approved</span>
                            @else
                                <span>pending</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<script>
function monitor() {
    return {
        lastRefresh: new Date().toLocaleTimeString(),
        startPolling() {
            setInterval(() => {
                window.location.reload();
            }, 30000);
        }
    }
}
</script>
</body>
</html>
