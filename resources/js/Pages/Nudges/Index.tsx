import InputLabel from '@/Components/InputLabel';
import StateBadge from '@/Components/StateBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/lib/format';
import { ActionItem, NudgeLog } from '@/types/tributary';
import { Head, Link, router, useForm } from '@inertiajs/react';

interface QueueEntry {
    item: ActionItem;
    draft: NudgeLog | null;
}

interface NudgesPageProps {
    queue: QueueEntry[];
    sentHistory: Array<
        NudgeLog & {
            action_item: Pick<ActionItem, 'id' | 'title' | 'current_state' | 'ball_with'>;
        }
    >;
}

function QueueDraftEditor({ draft }: { draft: NudgeLog }) {
    const form = useForm({
        message_text: draft.message_text,
        channel: draft.channel,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(route('nudges.update', draft.id), {
                    preserveScroll: true,
                });
            }}
            className="space-y-3"
        >
            <div>
                <InputLabel htmlFor={`draft-${draft.id}`} value="Draft message" />
                <textarea
                    id={`draft-${draft.id}`}
                    className="mt-1 h-24 w-full rounded-md border-gray-300 text-sm"
                    value={form.data.message_text}
                    onChange={(event) =>
                        form.setData('message_text', event.target.value)
                    }
                />
            </div>
            <div className="flex flex-wrap gap-2">
                <button
                    type="submit"
                    className="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                >
                    Save edit
                </button>
                <button
                    type="button"
                    onClick={() =>
                        form.post(route('nudges.approve', draft.id), {
                            preserveScroll: true,
                        })
                    }
                    className="rounded-md bg-green-600 px-3 py-1 text-sm font-semibold text-white hover:bg-green-500"
                >
                    Approve
                </button>
                <button
                    type="button"
                    onClick={() =>
                        form.post(route('nudges.skip', draft.id), {
                            preserveScroll: true,
                        })
                    }
                    className="rounded-md border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50"
                >
                    Skip
                </button>
                {draft.approved_at && !draft.sent_at && (
                    <button
                        type="button"
                        onClick={() =>
                            form.post(route('nudges.sent', draft.id), {
                                preserveScroll: true,
                            })
                        }
                        className="rounded-md bg-indigo-600 px-3 py-1 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Mark sent
                    </button>
                )}
            </div>
        </form>
    );
}

export default function NudgesIndex({ queue, sentHistory }: NudgesPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Nudge Queue
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Review pending nudges and recent sends.
                    </p>
                </div>
            }
        >
            <Head title="Nudges" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="font-semibold text-gray-900">
                                Pending approvals
                            </h3>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {queue.length === 0 ? (
                                <p className="p-5 text-sm text-gray-500">
                                    No pending nudges right now.
                                </p>
                            ) : (
                                queue.map((entry) => (
                                    <div key={entry.item.id} className="p-5">
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-gray-900">
                                                    {entry.item.title}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    Ball with {entry.item.ball_with} ·
                                                    Next nudge{' '}
                                                    {formatDateTime(
                                                        entry.item.next_nudge_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <StateBadge
                                                    state={entry.item.current_state}
                                                />
                                                <Link
                                                    href={route(
                                                        'items.show',
                                                        entry.item.id,
                                                    )}
                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                                                >
                                                    Open item
                                                </Link>
                                            </div>
                                        </div>

                                        {entry.draft ? (
                                            <QueueDraftEditor
                                                draft={entry.draft}
                                            />
                                        ) : (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'nudges.draft',
                                                            entry.item.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                            >
                                                Generate draft
                                            </button>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="font-semibold text-gray-900">
                                Sent nudge history
                            </h3>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {sentHistory.length === 0 ? (
                                <p className="p-5 text-sm text-gray-500">
                                    No sent nudges yet.
                                </p>
                            ) : (
                                sentHistory.map((nudge) => (
                                    <div key={nudge.id} className="p-5 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium text-gray-900">
                                                {nudge.action_item?.title ||
                                                    'Unknown item'}
                                            </span>
                                            {nudge.action_item && (
                                                <Link
                                                    href={route(
                                                        'items.show',
                                                        nudge.action_item.id,
                                                    )}
                                                    className="text-indigo-600 hover:text-indigo-700"
                                                >
                                                    View item
                                                </Link>
                                            )}
                                        </div>
                                        <p className="mt-1 text-gray-600">
                                            #{nudge.nudge_number} via{' '}
                                            {nudge.channel}
                                        </p>
                                        <p className="mt-1 text-gray-700">
                                            {nudge.message_text}
                                        </p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Sent {formatDateTime(nudge.sent_at)}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
