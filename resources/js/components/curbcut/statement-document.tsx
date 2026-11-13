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
    full: 'fully conforms to',
    partial: 'partially conforms to',
    none: 'does not conform to',
};

/** The statement itself; rendered on the public page and as the editor preview. */
export function StatementDocument({ s, headingLevel = 1 }: { s: PublicStatement; headingLevel?: 1 | 2 }) {
    const H = headingLevel === 1 ? 'h1' : 'h2';
    const Sub = headingLevel === 1 ? 'h2' : 'h3';
    const date = (iso: string | null) => (iso ? new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(iso)) : null);

    return (
        <article className="prose-sm max-w-none space-y-6 leading-relaxed">
            <header>
                <H className="text-2xl font-bold tracking-tight">Accessibility statement for {s.site_name}</H>
                <p className="text-muted-foreground mt-1">
                    {s.organisation} is committed to making{' '}
                    <a href={s.site_url} className="underline underline-offset-4">
                        {s.site_url}
                    </a>{' '}
                    accessible to everyone, including people with disabilities.
                </p>
            </header>

            <section>
                <Sub className="text-lg font-semibold">Conformance status</Sub>
                <p className="mt-2">
                    The Web Content Accessibility Guidelines (WCAG) define requirements for designers and developers to improve accessibility for
                    people with disabilities. This website <strong>{conformanceText[s.conformance]}</strong> {s.standard}.
                </p>
                <p className="mt-2">
                    <strong>{s.conformance_label}</strong>
                    {s.conformance === 'partial' && ': some parts of the content do not fully meet the standard.'}
                    {s.conformance === 'none' && ': most of the content does not meet the standard.'}
                    {s.conformance === 'full' && ': the content fully meets the standard without exceptions.'}
                </p>
            </section>

            {(s.failing.length > 0 || s.automated_rules.length > 0 || s.additional_limitations) && (
                <section>
                    <Sub className="text-lg font-semibold">Known limitations</Sub>
                    <p className="mt-2">Despite our efforts, some content is not yet fully accessible. We are working to fix the following:</p>
                    {s.failing.length > 0 && (
                        <ul className="mt-3 list-disc space-y-2 pl-5">
                            {s.failing.map((f) => (
                                <li key={f.id}>
                                    <strong>
                                        WCAG {f.id} {f.title}
                                    </strong>
                                    {f.notes
                                        ? `: ${f.notes}`
                                        : f.automated > 0
                                          ? `: automated testing found ${f.automated} ${f.automated === 1 ? 'instance' : 'instances'}.`
                                          : '.'}
                                </li>
                            ))}
                        </ul>
                    )}
                    {s.automated_rules.length > 0 && (
                        <>
                            <p className="mt-4">Problems found by our latest automated scan:</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {s.automated_rules.map((r) => (
                                    <li key={r.help}>
                                        {r.help} ({r.total} {r.total === 1 ? 'instance' : 'instances'}
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
                <Sub className="text-lg font-semibold">Feedback and contact information</Sub>
                {s.feedback_process && <p className="mt-2 whitespace-pre-line">{s.feedback_process}</p>}
                <ul className="mt-2 space-y-1">
                    {s.contact_email && (
                        <li>
                            Email:{' '}
                            <a href={`mailto:${s.contact_email}`} className="underline underline-offset-4">
                                {s.contact_email}
                            </a>
                        </li>
                    )}
                    {s.contact_phone && (
                        <li>
                            Phone:{' '}
                            <a href={`tel:${s.contact_phone.replace(/\s+/g, '')}`} className="underline underline-offset-4">
                                {s.contact_phone}
                            </a>
                        </li>
                    )}
                </ul>
            </section>

            {s.enforcement_procedure && (
                <section>
                    <Sub className="text-lg font-semibold">Enforcement procedure</Sub>
                    <p className="mt-2 whitespace-pre-line">{s.enforcement_procedure}</p>
                </section>
            )}

            <section>
                <Sub className="text-lg font-semibold">How we assessed this website</Sub>
                <p className="mt-2">
                    {s.organisation} assessed the accessibility of this website by self-evaluation, combining automated testing of{' '}
                    {s.pages_scanned ?? 0} pages with axe-core
                    {s.last_scan_at && ` (last run ${date(s.last_scan_at)})`} and a manual review of{' '}
                    {s.checklist_counts.total - s.checklist_counts.not_tested} of the {s.checklist_counts.total} WCAG 2.2 level A and AA success
                    criteria.
                </p>
            </section>

            <footer className="text-muted-foreground border-t pt-4 text-sm">
                {s.published_at && <p>This statement was first published on {date(s.published_at)}.</p>}
                {s.reviewed_at && <p>It was last reviewed on {date(s.reviewed_at)}.</p>}
                <p className="mt-2">
                    Generated with{' '}
                    <a href="https://github.com/mahamud894/curbcut" className="underline underline-offset-4">
                        Curbcut
                    </a>
                    , open-source accessibility compliance software.
                </p>
            </footer>
        </article>
    );
}
