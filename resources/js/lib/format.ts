import { getLocale, t } from '@/lib/i18n';

export function formatDate(iso: string | null | undefined): string {
    if (!iso) return t('Never');
    return new Intl.DateTimeFormat(getLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export function formatDay(iso: string | null | undefined): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat(getLocale(), { dateStyle: 'medium' }).format(new Date(iso));
}

export function formatNumber(n: number): string {
    return new Intl.NumberFormat(getLocale()).format(n);
}

export function relativeTime(iso: string | null | undefined): string {
    if (!iso) return t('never');
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(getLocale(), { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return t('just now');
}

export function shortUrl(url: string): string {
    try {
        const u = new URL(url);
        return u.pathname === '/' && !u.search ? u.host : `${u.pathname}${u.search}`;
    } catch {
        return url;
    }
}

export function capitalize(s: string): string {
    return s.charAt(0).toUpperCase() + s.slice(1);
}
