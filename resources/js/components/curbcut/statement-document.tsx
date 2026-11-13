import { tk, type Translator } from '@/lib/i18n';
import type { ReactNode } from 'react';

export interface PublicStatement {
    site_name: string;
    site_url: string;
    organisation: string;
    contact_email: string | null;
    contact_phone: string | null;
    standard: string;
    conformance: 'full' | 'partial' | 'none';
    conformance_label: string;
    is_override: boolean;
    suggested: 'full' | 'partial' | 'none';
    assessment_complete: boolean;
    checklist_counts: { pass: number; fail: number; not_applicable: number; not_tested: number; total: number };
    failing: { id: string; title: string; notes: string | null; automated: number }[];
    automated_rules: { help: string; total: number; criteria: string[] }[];
    additional_limitations: string | null;
    feedback_process: string | null;
    enforcement_procedure: string | null;
    last_scan_at: string | null;
    pages_scanned: number | null;
    reviewed_at: string | null;
    published_at: string | null;
}

const conformanceText = {
    full: tk('This website fully conforms to {standard}.'),
    partial: tk('This website partially conforms to {standard}.'),
    none: tk('This website does not conform to {standard}.'),
};

const conformanceDetail = {
    full: tk('<b>Fully conformant</b>: the content fully meets the standard without exceptions.'),
    partial: tk('<b>Partially conformant</b>: some parts of the content do not fully meet the standard.'),
    none: tk('<b>Not conformant</b>: most of the content does not meet the standard.'),
};

/**
 * The statement itself; rendered on the public page and as the editor preview,
 * in the statement's own language (which may differ from the interface language).
 */
export function StatementDocument({ s, headingLevel = 1, tr }: { s: PublicStatement; headingLevel?: 1 | 2; tr: Translator }) {
    const H = headingLevel === 1 ? 'h1' : 'h2';
    const Sub = headingLevel === 1 ? 'h2' : 'h3';
    const { t, rich } = tr;
    const date = (iso: string | null) => (iso ? new Intl.DateTimeFormat(tr.locale, { dateStyle: 'long' }).format(new Date(iso)) : '');
    const strong = (chunks: ReactNode) => <strong>{chunks}</strong>;
    const reviewed = s.checklist_counts.total - s.checklist_counts.not_tested;

    return (
        <article lang={tr.locale} className="prose-sm max-w-none space-y-6 leading-relaxed">
            <header>
                <H className="text-2xl font-bold tracking-tight">{t('Accessibility statement for {site}', { site: s.site_name })}</H>
                <p className="text-muted-foreground mt-1">
                    {rich('{organisation} is committed to making {site} accessible to everyone, including people with disabilities.', {
                        organisation: s.organisation,
                        site: (
                            <a href={s.site_url} className="underline underline-offset-4">
                                {s.site_url}
                            </a>
                        ),
                    })}
                </p>
            </header>

            <section>
                <Sub className="text-lg font-semibold">{t('Conformance status')}</Sub>
                <p className="mt-2">
                    {t(
                        'The Web Content Accessibility Guidelines (WCAG) define requirements for designers and developers to improve accessibility for people with disabilities.',
                    )}{' '}
                    <strong>{t(conformanceText[s.conformance], { standard: t(s.standard) })}</strong>
                </p>
                <p className="mt-2">{rich(conformanceDetail[s.conformance], { b: strong })}</p>
            </section>

            {(s.failing.length > 0 || s.automated_rules.length > 0 || s.additional_limitations) && (
                <section>
                    <Sub className="text-lg font-semibold">{t('Known limitations')}</Sub>
                    <p className="mt-2">{t('Despite our efforts, some content is not yet fully accessible. We are working to fix the following:')}</p>
                    {s.failing.length > 0 && (
                        <ul className="mt-3 list-disc space-y-2 pl-5">
                            {s.failing.map((f) => (
                                <li key={f.id}>
                                    <strong>
                                        WCAG {f.id} {t(f.title)}
                                    </strong>
                                    {f.notes
                                        ? `: ${f.notes}`
                                        : f.automated > 0
                                          ? t(': automated testing found {count, plural, one {# instance} other {# instances}}.', {
                                                count: f.automated,
                                            })
                                          : '.'}
                                </li>
                            ))}
                        </ul>
                    )}
                    {s.automated_rules.length > 0 && (
                        <>
                            <p className="mt-4">{t('Problems found by our latest automated scan:')}</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {s.automated_rules.map((r) => (
                                    <li key={r.help}>
                                        {r.help} ({t('{count, plural, one {# instance} other {# instances}}', { count: r.total })}
                                        {r.criteria.length > 0 && `, WCAG ${r.criteria.join(', ')}`})
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                    {s.additional_limitations && <p className="mt-4 whitespace-pre-line">{s.additional_limitations}</p>}
                </section>
            )}

            <section>
                <Sub className="text-lg font-semibold">{t('Feedback and contact information')}</Sub>
                {s.feedback_process && <p className="mt-2 whitespace-pre-line">{s.feedback_process}</p>}
                <ul className="mt-2 space-y-1">
                    {s.contact_email && (
                        <li>
                            {t('Email:')}{' '}
                            <a href={`mailto:${s.contact_email}`} className="underline underline-offset-4">
                                {s.contact_email}
                            </a>
                        </li>
                    )}
                    {s.contact_phone && (
                        <li>
                            {t('Phone:')}{' '}
                            <a href={`tel:${s.contact_phone.replace(/\s+/g, '')}`} className="underline underline-offset-4">
                                {s.contact_phone}
                            </a>
                        </li>
                    )}
                </ul>
            </section>

            {s.enforcement_procedure && (
                <section>
                    <Sub className="text-lg font-semibold">{t('Enforcement procedure')}</Sub>
                    <p className="mt-2 whitespace-pre-line">{s.enforcement_procedure}</p>
                </section>
            )}

            <section>
                <Sub className="text-lg font-semibold">{t('How we assessed this website')}</Sub>
                <p className="mt-2">
                    {s.last_scan_at
                        ? t(
                              '{organisation} assessed the accessibility of this website by self-evaluation, combining automated testing of {pages, plural, one {# page} other {# pages}} with axe-core (last run {date}) and a manual review of {reviewed} of the {total} WCAG 2.2 level A and AA success criteria.',
                              {
                                  organisation: s.organisation,
                                  pages: s.pages_scanned ?? 0,
                                  date: date(s.last_scan_at),
                                  reviewed,
                                  total: s.checklist_counts.total,
                              },
                          )
                        : t(
                              '{organisation} assessed the accessibility of this website by self-evaluation, combining automated testing of {pages, plural, one {# page} other {# pages}} with axe-core and a manual review of {reviewed} of the {total} WCAG 2.2 level A and AA success criteria.',
                              { organisation: s.organisation, pages: s.pages_scanned ?? 0, reviewed, total: s.checklist_counts.total },
                          )}
                </p>
            </section>

            <footer className="text-muted-foreground border-t pt-4 text-sm">
                {s.published_at && <p>{t('This statement was first published on {date}.', { date: date(s.published_at) })}</p>}
                {s.reviewed_at && <p>{t('It was last reviewed on {date}.', { date: date(s.reviewed_at) })}</p>}
                <p className="mt-2">
                    {rich('Generated with {curbcut}, open-source accessibility compliance software.', {
                        curbcut: (
                            <a href="https://github.com/mahamud894/curbcut" className="underline underline-offset-4">
                                Curbcut
                            </a>
                        ),
                    })}
                </p>
            </footer>
        </article>
    );
}
