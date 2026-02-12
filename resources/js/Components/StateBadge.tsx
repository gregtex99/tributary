import { ItemState } from '@/types/tributary';

const stateClasses: Record<string, string> = {
    action_needed: 'bg-yellow-100 text-yellow-800',
    waiting_on_reply: 'bg-blue-100 text-blue-800',
    waiting_on_calendar: 'bg-purple-100 text-purple-800',
    done: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-100 text-gray-700',
};

export default function StateBadge({ state }: { state: ItemState }) {
    const label = state.replaceAll('_', ' ');

    return (
        <span
            className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize ${stateClasses[state] ?? 'bg-gray-100 text-gray-700'}`}
        >
            {label}
        </span>
    );
}
