import { t } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useId } from 'react';

/**
 * Language menu. Saves the choice (on the account when signed in, in a cookie otherwise)
 * and reloads, so the whole page, including dates and numbers, switches language.
 */
export function LanguageSelect({ className = '' }: { className?: string }) {
    const { locale, locales } = usePage<SharedData>().props;
    const id = useId();

    const change = async (value: string) => {
        const token = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
        await fetch('/locale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ locale: value }),
            credentials: 'same-origin',
        });
        window.location.reload();
    };

    return (
        <div className={`flex items-center gap-2 ${className}`}>
            <Languages className="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
            <label htmlFor={id} className="sr-only">
                {t('Language')}
            </label>
            <select
                id={id}
                value={locale}
                onChange={(e) => change(e.target.value)}
                className="border-input bg-background h-8 rounded-md border px-2 text-sm"
            >
                {Object.entries(locales).map(([code, name]) => (
                    <option key={code} value={code} lang={code}>
                        {name}
                    </option>
                ))}
            </select>
        </div>
    );
}
