import StateBadge from '@/Components/StateBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ageFromNow, formatDateTime } from '@/lib/format';
import { ActionItem } from '@/types/tributary';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, Fragment, useMemo, useState } from 'react';

interface ItemsPageProps {
    items: {
        data: ActionItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        state: string;
        source: string;
        ball_with: string;
        sort: string;
        direction: 'asc' | 'desc';
    };
    filterOptions: {
        states: string[];
        sources: string[];
        ball_with: string[];
    };
}

export default function ItemsIndex({ items, filters, filterOptions }: ItemsPageProps) {
    const [form, setForm] = useState(filters);
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const sortLabel = useMemo(() => {
        if (form.sort === 'next_nudge_at') {
            return 'Next nudge';
        }

        return 'Created';
    }, [form.sort]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.get(route('items.index'), form, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Action Items
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Filter, sort, and inspect lifecycle status.
                    </p>
                </div>
            }
        >
            <Head title="Action Items" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5"
                    >
                        <select
                            value={form.state}
                            onChange={(event) =>
                                setForm((prev) => ({
                                    ...prev,
                                    state: event.target.value,
                                }))
                            }
                            className="rounded-md border-gray-300 text-sm"
                        >
                            <option value="">All states</option>
                            <option value="active">Active (non-done)</option>
                            {filterOptions.states.map((state) => (
                                <option key={state} value={state}>
                                    {state.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.source}
                            onChange={(event) =>
                                setForm((prev) => ({
                                    ...prev,
                                    source: event.target.value,
                                }))
                            }
                            className="rounded-md border-gray-300 text-sm"
                        >
                            <option value="">All sources</option>
                            {filterOptions.sources.map((source) => (
                                <option key={source} value={source}>
                                    {source}
                                </option>
                            ))}
                        </select>

                        <select
                            value={form.ball_with}
                            onChange={(event) =>
                                setForm((prev) => ({
                                    ...prev,
                                    ball_with: event.target.value,
                                }))
                            }
                            className="rounded-md border-gray-300 text-sm"
                        >
                            <option value="">All owners</option>
                            {filterOptions.ball_with.map((owner) => (
                                <option key={owner} value={owner}>
                                    {owner}
                                </option>
                            ))}
                        </select>

                        <div className="flex gap-2">
                            <select
                                value={form.sort}
                                onChange={(event) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        sort: event.target.value,
                                    }))
                                }
                                className="w-full rounded-md border-gray-300 text-sm"
                            >
                                <option value="created_at">Sort: created</option>
                                <option value="next_nudge_at">
                                    Sort: next nudge
                                </option>
                            </select>
                            <select
                                value={form.direction}
                                onChange={(event) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        direction: event.target.value as
                                            | 'asc'
                                            | 'desc',
                                    }))
                                }
                                className="rounded-md border-gray-300 text-sm"
                            >
                                <option value="desc">Desc</option>
                                <option value="asc">Asc</option>
                            </select>
                        </div>

                        <button
                            type="submit"
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Apply filters
                        </button>
                    </form>

                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Title</th>
                                    <th className="px-4 py-3">State</th>
                                    <th className="px-4 py-3">Ball with</th>
                                    <th className="px-4 py-3">Waiting for</th>
                                    <th className="px-4 py-3">Age</th>
                                    <th className="px-4 py-3">Nudges</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {items.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-6 text-center text-gray-500"
                                        >
                                            No items found with current filters.
                                        </td>
                                    </tr>
                                ) : (
                                    items.data.map((item) => (
                                        <Fragment key={item.id}>
                                            <tr>
                                                <td className="px-4 py-3">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setExpanded((prev) => ({
                                                                ...prev,
                                                                [item.id]:
                                                                    !prev[
                                                                        item.id
                                                                    ],
                                                            }))
                                                        }
                                                        className="text-left font-medium text-gray-900 hover:text-indigo-700"
                                                    >
                                                        {item.title}
                                                    </button>
                                                    <p className="text-xs text-gray-500">
                                                        {item.source}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StateBadge
                                                        state={item.current_state}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {item.ball_with}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {item.waiting_for || '—'}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {ageFromNow(item.created_at)}
                                                </td>
                                                <td className="px-4 py-3 text-gray-700">
                                                    {item.nudge_count}/
                                                    {item.max_nudges}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Link
                                                        href={route(
                                                            'items.show',
                                                            item.id,
                                                        )}
                                                        className="font-medium text-indigo-600 hover:text-indigo-700"
                                                    >
                                                        View details
                                                    </Link>
                                                </td>
                                            </tr>
                                            {expanded[item.id] && (
                                                <tr>
                                                    <td
                                                        colSpan={7}
                                                        className="bg-gray-50 px-4 py-4 text-sm text-gray-700"
                                                    >
                                                        <p>
                                                            <span className="font-medium">
                                                                Description:
                                                            </span>{' '}
                                                            {item.description ||
                                                                'No description'}
                                                        </p>
                                                        <p className="mt-2">
                                                            <span className="font-medium">
                                                                Next nudge:
                                                            </span>{' '}
                                                            {formatDateTime(
                                                                item.next_nudge_at,
                                                            )}
                                                        </p>
                                                        <p className="mt-1">
                                                            <span className="font-medium">
                                                                Created:
                                                            </span>{' '}
                                                            {formatDateTime(
                                                                item.created_at,
                                                            )}
                                                        </p>
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-gray-500">
                            Sorted by {sortLabel} ({form.direction})
                        </span>
                        {items.links.map((link, index) => (
                            <button
                                key={`${link.label}-${index}`}
                                type="button"
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.visit(link.url, {
                                        preserveScroll: true,
                                        preserveState: true,
                                    })
                                }
                                className={`rounded-md border px-3 py-1 ${
                                    link.active
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-300 text-gray-700 disabled:opacity-40'
                                }`}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
