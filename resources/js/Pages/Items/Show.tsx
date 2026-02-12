import InputLabel from '@/Components/InputLabel';
import StateBadge from '@/Components/StateBadge';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/lib/format';
import { ActionItem } from '@/types/tributary';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface ItemShowProps {
    item: ActionItem;
    stateOptions: string[];
}

export default function ItemShow({ item, stateOptions }: ItemShowProps) {
    const editForm = useForm({
        title: item.title,
        description: item.description ?? '',
        ball_with: item.ball_with,
        waiting_for: item.waiting_for ?? '',
        nudge_after_hours: item.nudge_after_hours,
        max_nudges: item.max_nudges,
    });

    const transitionForm = useForm({
        to_state: item.current_state,
        notes: '',
    });

    const submitEdit = (event: FormEvent) => {
        event.preventDefault();

        editForm.patch(route('items.update', item.id), {
            preserveScroll: true,
        });
    };

    const submitTransition = (event: FormEvent) => {
        event.preventDefault();

        transitionForm.post(route('items.transition', item.id), {
            preserveScroll: true,
            onSuccess: () => transitionForm.setData('notes', ''),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {item.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Source: {item.source} · Created{' '}
                            {formatDateTime(item.created_at)}
                        </p>
                    </div>
                    <Link
                        href={route('items.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-700"
                    >
                        Back to list
                    </Link>
                </div>
            }
        >
            <Head title={`Item: ${item.title}`} />

            <div className="py-8">
                <div className="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
                    <section className="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Full item info
                        </h3>

                        <dl className="grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-gray-500">State</dt>
                                <dd className="mt-1">
                                    <StateBadge state={item.current_state} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Ball with</dt>
                                <dd className="mt-1 text-gray-900">
                                    {item.ball_with}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Waiting for</dt>
                                <dd className="mt-1 text-gray-900">
                                    {item.waiting_for || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Source ref</dt>
                                <dd className="mt-1 text-gray-900">
                                    {item.source_ref || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Next nudge</dt>
                                <dd className="mt-1 text-gray-900">
                                    {formatDateTime(item.next_nudge_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-gray-500">Resolved</dt>
                                <dd className="mt-1 text-gray-900">
                                    {formatDateTime(item.resolved_at)}
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h4 className="text-sm font-semibold text-gray-700">
                                Description
                            </h4>
                            <p className="mt-2 whitespace-pre-wrap text-sm text-gray-700">
                                {item.description || 'No description provided.'}
                            </p>
                        </div>
                    </section>

                    <section className="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Manual state change
                        </h3>

                        <form onSubmit={submitTransition} className="space-y-3">
                            <div>
                                <InputLabel htmlFor="to_state" value="State" />
                                <select
                                    id="to_state"
                                    value={transitionForm.data.to_state}
                                    onChange={(event) =>
                                        transitionForm.setData(
                                            'to_state',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-md border-gray-300 text-sm"
                                >
                                    {stateOptions.map((state) => (
                                        <option key={state} value={state}>
                                            {state.replaceAll('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <InputLabel htmlFor="notes" value="Notes" />
                                <textarea
                                    id="notes"
                                    value={transitionForm.data.notes}
                                    onChange={(event) =>
                                        transitionForm.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 h-24 w-full rounded-md border-gray-300 text-sm"
                                />
                            </div>

                            <button
                                type="submit"
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                disabled={transitionForm.processing}
                            >
                                Update state
                            </button>
                        </form>
                    </section>

                    <section className="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Timeline
                        </h3>
                        <div className="space-y-3 text-sm">
                            {(item.transitions ?? []).length === 0 ? (
                                <p className="text-gray-500">No transitions yet.</p>
                            ) : (
                                item.transitions?.map((transition) => (
                                    <div
                                        key={transition.id}
                                        className="rounded-md border border-gray-200 p-3"
                                    >
                                        <p className="font-medium text-gray-900">
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
                                        <p className="text-gray-600">
                                            Trigger: {transition.trigger}
                                        </p>
                                        {transition.notes && (
                                            <p className="text-gray-600">
                                                Notes: {transition.notes}
                                            </p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-500">
                                            {formatDateTime(transition.created_at)}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Nudge history
                        </h3>
                        <div className="space-y-3 text-sm">
                            {(item.nudges ?? []).length === 0 ? (
                                <p className="text-gray-500">No nudges logged yet.</p>
                            ) : (
                                item.nudges?.map((nudge) => (
                                    <div
                                        key={nudge.id}
                                        className="rounded-md border border-gray-200 p-3"
                                    >
                                        <p className="font-medium text-gray-900">
                                            Nudge #{nudge.nudge_number}
                                        </p>
                                        <p className="text-gray-600">
                                            {nudge.message_text}
                                        </p>
                                        <p className="mt-1 text-xs text-gray-500">
                                            Approved:{' '}
                                            {formatDateTime(nudge.approved_at)} ·
                                            Sent: {formatDateTime(nudge.sent_at)}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-3">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Edit item
                        </h3>
                        <form
                            onSubmit={submitEdit}
                            className="mt-4 grid gap-4 md:grid-cols-2"
                        >
                            <div className="md:col-span-2">
                                <InputLabel htmlFor="title" value="Title" />
                                <TextInput
                                    id="title"
                                    value={editForm.data.title}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <InputLabel
                                    htmlFor="description"
                                    value="Description"
                                />
                                <textarea
                                    id="description"
                                    value={editForm.data.description}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 h-28 w-full rounded-md border-gray-300 text-sm"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="ball_with" value="Ball with" />
                                <TextInput
                                    id="ball_with"
                                    value={editForm.data.ball_with}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'ball_with',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="waiting_for"
                                    value="Waiting for"
                                />
                                <TextInput
                                    id="waiting_for"
                                    value={editForm.data.waiting_for}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'waiting_for',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="nudge_after_hours"
                                    value="First nudge after (hours)"
                                />
                                <TextInput
                                    id="nudge_after_hours"
                                    type="number"
                                    min={1}
                                    value={editForm.data.nudge_after_hours}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'nudge_after_hours',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="max_nudges"
                                    value="Max nudges"
                                />
                                <TextInput
                                    id="max_nudges"
                                    type="number"
                                    min={1}
                                    value={editForm.data.max_nudges}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'max_nudges',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                            </div>

                            <div className="md:col-span-2">
                                <button
                                    type="submit"
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                    disabled={editForm.processing}
                                >
                                    Save changes
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
