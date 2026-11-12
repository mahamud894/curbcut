export function formatDate(iso: string | null | undefined): string {
    if (!iso) return 'Never';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

export function relativeTime(iso: string | null | undefined): string {
    if (!iso) return 'never';
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit);
    }
    return 'just now';
}

export function shortUrl(url: string): string {
    try {
        const u = new URL(url);
        return u.pathname === '/' && !u.search ? u.host : `${u.pathname}${u.search}`;
    } catch {
        return url;
    }
}

export function plural(count: number, word: string, pluralWord = `${word}s`): string {
    return `${count.toLocaleString()} ${count === 1 ? word : pluralWord}`;
}
