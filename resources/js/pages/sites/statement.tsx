import { StatementDocument, type PublicStatement } from '@/components/curbcut/statement-document';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SiteLayout from '@/layouts/site-layout';
import type { SiteSummary } from '@/types/curbcut';
import { router, useForm } from '@inertiajs/react';
import { Check, Copy, ExternalLink, Globe, LoaderCircle, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface StatementForm {
    organisation: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    conformance_override: string | null;
    additional_limitations: string | null;
    feedback_process: string | null;
    enforcement_procedure: string | null;
    published: boolean;
    published_at: string | null;
    public_url: string;
}

interface Props {
    site: SiteSummary;
    statement: StatementForm;
    preview: PublicStatement;
    conformanceLabels: Record<string, string>;
}

const textareaClass = 'border-input bg-background rounded-md border px-3 py-2 text-sm';

export default function StatementEditor({ site, statement, preview, conformanceLabels }: Props) {
    const { data, setData, put, processing, errors, isDirty } = useForm({
        organisation: statement.organisation ?? '',
        contact_email: statement.contact_email ?? '',
        contact_phone: statement.contact_phone ?? '',
        conformance_override: statement.conformance_override ?? '',
        additional_limitations: statement.additional_limitations ?? '',
        feedback_process: statement.feedback_process ?? '',
        enforcement_procedure: statement.enforcement_procedure ?? '',
    });
    const [copied, setCopied] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/sites/${site.id}/statement`, {
            preserveScroll: true,
        });
    };

    const copy = async () => {
        await navigator.clipboard.writeText(statement.public_url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <SiteLayout site={site} title="Accessibility statement">
            <section
                aria-labelledby="publish-heading"
                className="bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5 shadow-xs"
            >
                <div>
                    <h2 id="publish-heading" className="flex items-center gap-2 font-semibold">
                        <Globe className="size-4" aria-hidden="true" />
                        {statement.published ? 'Published' : 'Not published'}
                    </h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {statement.published
                            ? 'The public page updates automatically after every scan and checklist change. Link to it from your website footer.'
                            : 'Publish to get a public page you can link from your website, as the European Accessibility Act and similar laws require.'}
                    </p>
                    {statement.published && (
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <code className="bg-muted rounded px-2 py-1 text-xs break-all">{statement.public_url}</code>
                            <Button size="sm" variant="outline" onClick={copy}>
                                {copied ? <Check aria-hidden="true" /> : <Copy aria-hidden="true" />}
                                {copied ? 'Copied' : 'Copy link'}
                            </Button>
                            <a
                                href={statement.public_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                            >
                                Open <ExternalLink className="size-3.5" aria-hidden="true" />
                                <span className="sr-only">(opens in a new tab)</span>
                            </a>
                        </div>
                    )}
                </div>
                <Button
                    variant={statement.published ? 'outline' : 'default'}
                    onClick={() => router.put(`/sites/${site.id}/statement/publish`, { published: !statement.published }, { preserveScroll: true })}
                >
                    {statement.published ? 'Unpublish' : 'Publish statement'}
                </Button>
            </section>

            {!preview.assessment_complete && (
                <div
                    role="note"
                    className="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
                >
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>
                        {preview.checklist_counts.not_tested} of {preview.checklist_counts.total} WCAG criteria have not been reviewed yet. Complete
                        the{' '}
                        <a href={`/sites/${site.id}/checklist`} className="font-medium underline underline-offset-4">
                            checklist
                        </a>{' '}
                        before claiming conformance.
                    </p>
                </div>
            )}

            <div className="grid gap-6 xl:grid-cols-[minmax(0,460px)_1fr]">
                <form onSubmit={submit} className="bg-card grid content-start gap-5 rounded-xl border p-5 shadow-xs">
                    <h2 className="font-semibold">Details</h2>
                    <div className="grid gap-2">
                        <Label htmlFor="organisation">Organisation name</Label>
                        <Input
                            id="organisation"
                            value={data.organisation}
                            onChange={(e) => setData('organisation', e.target.value)}
                            placeholder="Acme Ltd"
                        />
                        <InputError message={errors.organisation} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="contact_email">Contact email</Label>
                            <Input
                                id="contact_email"
                                type="email"
                                value={data.contact_email}
                                onChange={(e) => setData('contact_email', e.target.value)}
                                placeholder="accessibility@example.com"
                            />
                            <InputError message={errors.contact_email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="contact_phone">
                                Phone <span className="text-muted-foreground font-normal">(optional)</span>
                            </Label>
                            <Input
                                id="contact_phone"
                                type="tel"
                                value={data.contact_phone}
                                onChange={(e) => setData('contact_phone', e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="conformance_override">Conformance status</Label>
                        <select
                            id="conformance_override"
                            value={data.conformance_override}
                            onChange={(e) => setData('conformance_override', e.target.value)}
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            aria-describedby="conformance-help"
                        >
                            <option value="">Automatic: {conformanceLabels[preview.suggested]}</option>
                            {Object.entries(conformanceLabels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <p id="conformance-help" className="text-muted-foreground text-xs">
                            Automatic uses your checklist and open issues. Choose a status yourself only if an auditor has assessed the site.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="additional_limitations">Other known limitations</Label>
                        <textarea
                            id="additional_limitations"
                            rows={4}
                            value={data.additional_limitations}
                            onChange={(e) => setData('additional_limitations', e.target.value)}
                            className={textareaClass}
                            placeholder="For example: PDF documents published before 2024 are not fully accessible. We plan to replace them by June 2027."
                        />
                        <p className="text-muted-foreground text-xs">Failing checklist items and automated findings are listed automatically.</p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="feedback_process">Feedback process</Label>
                        <textarea
                            id="feedback_process"
                            rows={3}
                            value={data.feedback_process}
                            onChange={(e) => setData('feedback_process', e.target.value)}
                            className={textareaClass}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="enforcement_procedure">
                            Enforcement procedure <span className="text-muted-foreground font-normal">(optional)</span>
                        </Label>
                        <textarea
                            id="enforcement_procedure"
                            rows={3}
                            value={data.enforcement_procedure}
                            onChange={(e) => setData('enforcement_procedure', e.target.value)}
                            className={textareaClass}
                            placeholder="Which authority people can contact if they are not satisfied with our response."
                        />
                    </div>
                    <Button type="submit" disabled={processing || !isDirty} className="w-fit">
                        {processing && <LoaderCircle className="animate-spin" aria-hidden="true" />}
                        Save statement
                    </Button>
                </form>

                <section aria-labelledby="preview-heading" className="bg-card rounded-xl border shadow-xs">
                    <h2 id="preview-heading" className="text-muted-foreground border-b px-5 py-3 text-sm font-semibold">
                        Preview {isDirty && '(save to update)'}
                    </h2>
                    <div className="p-6">
                        <StatementDocument s={preview} headingLevel={2} />
                    </div>
                </section>
            </div>
        </SiteLayout>
    );
}
