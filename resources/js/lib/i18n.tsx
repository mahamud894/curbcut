import { IntlMessageFormat } from 'intl-messageformat';
import { cloneElement, isValidElement, type ReactNode } from 'react';

/**
 * Translations live in lang/<locale>.json, keyed by the English text, and are shared with
 * Laravel's __() and tr(). Values are ICU messages:
 *   t('Add site')
 *   t('{count, plural, one {# page} other {# pages}}', { count: 3 })
 */
type Dictionary = Record<string, string>;
type Values = Record<string, string | number | null | undefined>;

const loaders = import.meta.glob<Dictionary>('../../../lang/*.json', { import: 'default' });
const dictionaries: Record<string, Dictionary> = { en: {} };
const formatters = new Map<string, IntlMessageFormat>();

let current = 'en';

export async function loadLocale(locale: string): Promise<void> {
    if (!dictionaries[locale]) {
        const loader = loaders[`../../../lang/${locale}.json`];
        dictionaries[locale] = loader ? await loader() : {};
    }
}

export async function setLocale(locale: string): Promise<void> {
    await loadLocale(locale);
    current = locale;
    document.documentElement.lang = locale;
}

export function getLocale(): string {
    return current;
}

/**
 * The locale for Intl formatting: always Latin digits, so numbers and dates match the
 * counts shown elsewhere (Bengali would otherwise mix ৪ and 4 on one screen).
 */
export function intlLocale(locale: string = current): string {
    return `${locale}-u-nu-latn`;
}

export function translate(locale: string, key: string, values?: Values): string {
    const message = dictionaries[locale]?.[key] || key;
    if (!values && !message.includes('{')) return message;
    const cacheKey = `${locale}\u0000${message}`;
    let formatter = formatters.get(cacheKey);
    if (!formatter) {
        try {
            formatter = new IntlMessageFormat(message, intlLocale(locale));
        } catch {
            return message;
        }
        formatters.set(cacheKey, formatter);
    }
    try {
        return String(formatter.format(values as Record<string, string | number>));
    } catch {
        return message;
    }
}

/**
 * Like translate(), but values may be React elements (links, <strong>), which are kept as
 * elements: tRich(locale, '{org} is committed to making {site} accessible', { org, site: <a .../> }).
 */
export function translateRich(locale: string, key: string, values: Record<string, unknown>): ReactNode {
    const message = dictionaries[locale]?.[key] || key;
    try {
        const parts = new IntlMessageFormat(message, intlLocale(locale)).format(values as Record<string, string>);
        return Array.isArray(parts) ? parts.map((part, i) => (isValidElement(part) ? cloneElement(part, { key: i }) : part)) : (parts as ReactNode);
    } catch {
        return message;
    }
}

/** Translate into the interface language. */
export function t(key: string, values?: Values): string {
    return translate(current, key, values);
}

/** Translate into the interface language, keeping React elements given as values. */
export function tRich(key: string, values: Record<string, unknown>): ReactNode {
    return translateRich(current, key, values);
}

export interface Translator {
    locale: string;
    t: typeof t;
    rich: typeof tRich;
}

/** A translator for another language, e.g. an accessibility statement in the site's language. */
export function translatorFor(locale: string): Translator {
    return { locale, t: (key, values) => translate(locale, key, values), rich: (key, values) => translateRich(locale, key, values) };
}

/** Marks a string for translation where it is defined (e.g. in a menu list); translate it with t() where it is shown. */
export function tk<T extends string>(key: T): T {
    return key;
}
