export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

export function formatDuration(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '—';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);

    if (hours <= 0) {
        return `${minutes}m`;
    }

    if (hours < 24) {
        return `${hours}h ${minutes}m`;
    }

    const days = Math.floor(hours / 24);
    const remHours = hours % 24;

    return `${days}d ${remHours}h`;
}

export function ageFromNow(value: string): string {
    const created = new Date(value).getTime();
    const diffMs = Date.now() - created;

    if (diffMs < 0) {
        return '0m';
    }

    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours}h`;
    }

    const days = Math.floor(hours / 24);

    return `${days}d`;
}
