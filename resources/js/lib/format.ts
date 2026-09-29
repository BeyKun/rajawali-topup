/**
 * Formatting helpers shared across the admin dashboard.
 */

/**
 * Format a number into an Indonesian Rupiah string (e.g. Rp 15.000).
 */
export function formatRupiah(value: number | string | null | undefined): string {
    const amount = typeof value === 'string' ? Number(value) : (value ?? 0);

    if (!Number.isFinite(amount)) {
        return 'Rp 0';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

/**
 * Format an ISO date/time string into a readable Indonesian date.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

/**
 * Format an ISO date string into a short Indonesian date (no time).
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date);
}

/**
 * Format an ISO date string into a compact `dd MMM` chart label.
 */
export function formatShortDay(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
    }).format(date);
}
