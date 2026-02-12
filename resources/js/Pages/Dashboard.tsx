import StateBadge from '@/Components/StateBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime, formatDuration } from '@/lib/format';
import { ActionItem, ActionItemTransition } from '@/types/tributary';
import { Head, Link } from '@inertiajs/react';

interface DashboardProps {
    stats: {
        active_items: number;
        pending_nudges: number;
        signals_today: number;
        avg_resolution_seconds: number;
    };
    recentTransitions: Array<
        ActionItemTransition & {
            action_item: Pick<ActionItem, 'id' | 'title' | 'current_state'> | null;
        }
    >;
    attentionItems: ActionItem[];
}

const statCards = [
    {
        key: 'active_items',
        label: 'Active items',
    },
    {
        key: 'pending_nudges',
        label: 'Pending nudges',
    },
    {
        key: 'signals_today',
        label: 'Signals today',
    },
] as const;

export default function Dashboard({
    stats,
    recentTransitions,
    attentionItems,
}: DashboardProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Tributary Overview
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {statCards.map((card) => (
                            <div
                                key={card.key}
                                className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"
                            >
                                <p className="text-sm text-gray-500">{card.label}</p>
                                <p className="mt-2 text-3xl font-semibold text-gray-900">
                                    {stats[card.key]}
                                </p>
                            </div>
                        ))}

                        <div className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <p className="text-sm text-gray-500">
                                Avg resolution time
                            </p>
                            <p className="mt-2 text-3xl font-semibold text-gray-900">
                                {formatDuration(stats.avg_resolution_seconds)}
                            </p>
                        </div>
                    </section>

                    <section className="grid gap-6 lg:grid-cols-2">
                        <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-200 px-5 py-4">
                                <h3 className="font-semibold text-gray-900">
                                    Recent activity
                                </h3>
                                <p className="text-sm text-gray-500">
                                    Last 10 state transitions
                                </p>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {recentTransitions.length === 0 ? (
                                    <p className="p-5 text-sm text-gray-500">
                                        No transitions yet.
                                    </p>
                                ) : (
                                    recentTransitions.map((transition) => (
                                        <div key={transition.id} className="p-5 text-sm">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium text-gray-900">
                                                    {transition.action_item?.title ??
                                                        'Unknown item'}
                                                </span>
                                                {transition.action_item && (
                                                    <Link
                                                        href={route(
                                                            'items.show',
                                                            transition.action_item.id,
                                                        )}
                                                        className="text-indigo-600 hover:text-indigo-700"
                                                    >
                                                        View
                                                    </Link>
                                                )}
                                            </div>
                                            <p className="mt-1 text-gray-600">
                                                {transition.from_state.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}{' '}
                                                →{' '}
                                                {transition.to_state.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500">
                                                Trigger: {transition.trigger} ·{' '}
                                                {formatDateTime(
                                                    transition.created_at,
                                                )}
                                            </p>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div className="border-b border-gray-200 px-5 py-4">
                                <h3 className="font-semibold text-gray-900">
                                    Items needing attention
                                </h3>
                                <p className="text-sm text-gray-500">
                                    action_needed + ball_with=greg
                                </p>
                            </div>
                            <div className="divide-y divide-gray-100">
                                {attentionItems.length === 0 ? (
                                    <p className="p-5 text-sm text-gray-500">
                                        Nothing urgent right now.
                                    </p>
                                ) : (
                                    attentionItems.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-4 p-5"
                                        >
                                            <div>
                                                <p className="font-medium text-gray-900">
                                                    {item.title}
                                                </p>
                                                <p className="mt-1 text-sm text-gray-500">
                                                    Source: {item.source} · Created{' '}
                                                    {formatDateTime(
                                                        item.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <StateBadge
                                                    state={item.current_state}
                                                />
                                                <Link
                                                    href={route(
                                                        'items.show',
                                                        item.id,
                                                    )}
                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                                >
                                                    Open
                                                </Link>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
