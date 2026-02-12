import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { NudgeRule } from '@/types/tributary';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface SettingsProps {
    rules: NudgeRule[];
    defaultOrgId: string;
}

function RuleRow({ rule }: { rule: NudgeRule }) {
    const form = useForm({
        id: rule.id,
        org_id: rule.org_id,
        item_type: rule.item_type,
        first_nudge_hours: rule.first_nudge_hours,
        repeat_nudge_hours: rule.repeat_nudge_hours,
        max_nudges: rule.max_nudges,
        auto_send: rule.auto_send,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('settings.rules.store'), {
            preserveScroll: true,
        });
    };

    return (
        <tr className="border-t border-gray-100">
            <td className="px-3 py-3 text-sm text-gray-700">{rule.org_id}</td>
            <td className="px-3 py-3">
                <TextInput
                    value={form.data.item_type}
                    onChange={(event) =>
                        form.setData('item_type', event.target.value)
                    }
                    className="w-full"
                />
            </td>
            <td className="px-3 py-3">
                <TextInput
                    type="number"
                    min={1}
                    value={form.data.first_nudge_hours}
                    onChange={(event) =>
                        form.setData(
                            'first_nudge_hours',
                            Number(event.target.value),
                        )
                    }
                    className="w-24"
                />
            </td>
            <td className="px-3 py-3">
                <TextInput
                    type="number"
                    min={1}
                    value={form.data.repeat_nudge_hours}
                    onChange={(event) =>
                        form.setData(
                            'repeat_nudge_hours',
                            Number(event.target.value),
                        )
                    }
                    className="w-24"
                />
            </td>
            <td className="px-3 py-3">
                <TextInput
                    type="number"
                    min={1}
                    value={form.data.max_nudges}
                    onChange={(event) =>
                        form.setData('max_nudges', Number(event.target.value))
                    }
                    className="w-20"
                />
            </td>
            <td className="px-3 py-3 text-center">
                <Checkbox
                    checked={form.data.auto_send}
                    onChange={(event) =>
                        form.setData('auto_send', event.target.checked)
                    }
                />
            </td>
            <td className="px-3 py-3">
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={submit}
                        className="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={() =>
                            form.delete(route('settings.rules.destroy', rule.id), {
                                preserveScroll: true,
                            })
                        }
                        className="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                    >
                        Delete
                    </button>
                </div>
            </td>
        </tr>
    );
}

export default function SettingsIndex({ rules, defaultOrgId }: SettingsProps) {
    const createForm = useForm({
        org_id: defaultOrgId,
        item_type: '',
        first_nudge_hours: 48,
        repeat_nudge_hours: 48,
        max_nudges: 3,
        auto_send: false,
    });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();

        createForm.post(route('settings.rules.store'), {
            preserveScroll: true,
            onSuccess: () =>
                createForm.setData({
                    org_id: defaultOrgId,
                    item_type: '',
                    first_nudge_hours: 48,
                    repeat_nudge_hours: 48,
                    max_nudges: 3,
                    auto_send: false,
                }),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Settings
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure nudge rules by item type.
                    </p>
                </div>
            }
        >
            <Head title="Settings" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 className="text-lg font-semibold text-gray-900">
                            Add new rule
                        </h3>
                        <form
                            onSubmit={submitCreate}
                            className="mt-4 grid gap-4 md:grid-cols-6"
                        >
                            <div>
                                <InputLabel htmlFor="org_id" value="Org" />
                                <TextInput
                                    id="org_id"
                                    value={createForm.data.org_id}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'org_id',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="item_type"
                                    value="Item type"
                                />
                                <TextInput
                                    id="item_type"
                                    value={createForm.data.item_type}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'item_type',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="first_nudge_hours"
                                    value="First (hrs)"
                                />
                                <TextInput
                                    id="first_nudge_hours"
                                    type="number"
                                    min={1}
                                    value={createForm.data.first_nudge_hours}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'first_nudge_hours',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="repeat_nudge_hours"
                                    value="Repeat (hrs)"
                                />
                                <TextInput
                                    id="repeat_nudge_hours"
                                    type="number"
                                    min={1}
                                    value={createForm.data.repeat_nudge_hours}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'repeat_nudge_hours',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-1 w-full"
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
                                    value={createForm.data.max_nudges}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'max_nudges',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <label className="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                    <Checkbox
                                        checked={createForm.data.auto_send}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'auto_send',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Auto send
                                </label>
                                <button
                                    type="submit"
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    Add
                                </button>
                            </div>
                        </form>
                    </section>

                    <section className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-200 px-5 py-4">
                            <h3 className="font-semibold text-gray-900">
                                Nudge rules
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th className="px-3 py-3">Org</th>
                                        <th className="px-3 py-3">Item Type</th>
                                        <th className="px-3 py-3">First</th>
                                        <th className="px-3 py-3">Repeat</th>
                                        <th className="px-3 py-3">Max</th>
                                        <th className="px-3 py-3">Auto</th>
                                        <th className="px-3 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rules.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-6 text-center text-sm text-gray-500"
                                            >
                                                No rules yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        rules.map((rule) => (
                                            <RuleRow
                                                key={rule.id}
                                                rule={rule}
                                            />
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
