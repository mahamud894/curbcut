import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { relativeTime } from '@/lib/format';
import { t, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Assessment, ChecklistStatus, Criterion, SiteSummary } from '@/types/curbcut';
import { Link, router } from '@inertiajs/react';
import { Bot, ExternalLink } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    site: SiteSummary;
    assessment: Assessment;
    principles: Record<string, string>;
}

const statusOptions: { value: ChecklistStatus; label: string; active: string }[] = [
    { value: 'pass', label: tk('Pass'), active: 'bg-emerald-700 text-white border-emerald-700' },
    { value: 'fail', label: tk('Fail'), active: 'bg-red-700 text-white border-red-700' },
    { value: 'not_applicable', label: tk('N/A'), active: 'bg-slate-700 text-white border-slate-700' },
    { value: 'not_tested', label: tk('Not tested'), active: 'bg-foreground text-background border-foreground' },
];

type Filter = 'all' | ChecklistStatus | 'automated';

export default function Checklist({ site, assessment, principles }: Props) {
    const [filter, setFilter] = useState<Filter>('all');
    const { counts } = assessment;
    const tested = counts.total - counts.not_tested;

    const groups = useMemo(() => {
        const visible = assessment.criteria.filter((c) =>
            filter === 'all' ? true : filter === 'automated' ? c.automated_issues > 0 : c.status === filter,
        );
        return Object.values(principles)
            .map((principle) => ({ principle, items: visible.filter((c) => c.principle === principle) }))
            .filter((g) => g.items.length > 0);
    }, [assessment.criteria, filter, principles]);

    return (
        <SiteLayout site={site} title={t('WCAG checklist')}>
            <section aria-labelledby="progress-heading" className="bg-card rounded-xl border p-5 shadow-xs">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 id="progress-heading" className="font-semibold">
                            {t('Manual review against WCAG 2.2 AA')}
                        </h2>
                        <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                            {t(
                                'Automated scans catch only part of WCAG. Test each success criterion by hand (keyboard, screen reader, zoom) and record the result here. Criteria with open automated issues are counted as failing.',
                            )}
                        </p>
                    </div>
                    <p className="text-sm">
                        <span className="text-2xl font-bold tabular-nums">{tested}</span>
                        <span className="text-muted-foreground"> {t('/ {total} reviewed', { total: counts.total })}</span>
                    </p>
                </div>
                <div
                    className="bg-muted mt-4 flex h-3 overflow-hidden rounded-full"
                    role="img"
                    aria-label={t('{pass} passing, {fail} failing, {na} not applicable, {untested} not tested', {
                        pass: counts.pass,
                        fail: counts.fail,
                        na: counts.not_applicable,
                        untested: counts.not_tested,
                    })}
                >
                    <div className="bg-emerald-600" style={{ width: `${(counts.pass / counts.total) * 100}%` }} />
                    <div className="bg-red-600" style={{ width: `${(counts.fail / counts.total) * 100}%` }} />
                    <div className="bg-slate-400" style={{ width: `${(counts.not_applicable / counts.total) * 100}%` }} />
                </div>
                <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                    <li>
                        <span className="mr-1.5 inline-block size-2.5 rounded-full bg-emerald-600" aria-hidden="true" />
                        {t('{count} pass', { count: counts.pass })}
                    </li>
                    <li>
                        <span className="mr-1.5 inline-block size-2.5 rounded-full bg-red-600" aria-hidden="true" />
                        {t('{count} fail', { count: counts.fail })}
                    </li>
                    <li>
                        <span className="mr-1.5 inline-block size-2.5 rounded-full bg-slate-400" aria-hidden="true" />
                        {t('{count} not applicable', { count: counts.not_applicable })}
                    </li>
                    <li className="text-muted-foreground">{t('{count} not tested', { count: counts.not_tested })}</li>
                    {assessment.last_tested_at && (
                        <li className="text-muted-foreground">{t('Last updated {when}', { when: relativeTime(assessment.last_tested_at) })}</li>
                    )}
                </ul>
            </section>

            <div role="group" aria-label={t('Show criteria')} className="flex flex-wrap gap-2">
                {(
                    [
                        ['all', tk('All')],
                        ['not_tested', tk('Not tested')],
                        ['fail', tk('Failing')],
                        ['automated', tk('With automated issues')],
                        ['pass', tk('Passing')],
                        ['not_applicable', tk('Not applicable')],
                    ] as [Filter, string][]
                ).map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        aria-pressed={filter === value}
                        onClick={() => setFilter(value)}
                        className={cn(
                            'rounded-full border px-3 py-1 text-sm',
                            filter === value ? 'bg-foreground text-background border-foreground' : 'hover:bg-muted',
                        )}
                    >
                        {t(label)}
                    </button>
                ))}
            </div>

            {groups.length === 0 && (
                <p className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">{t('No criteria match this filter.')}</p>
            )}

            {groups.map((group) => (
                <section key={group.principle} aria-labelledby={`principle-${group.principle}`} className="bg-card rounded-xl border shadow-xs">
                    <h2 id={`principle-${group.principle}`} className="border-b px-5 py-3 font-semibold">
                        {t(group.principle)}
                    </h2>
                    <ul className="divide-y">
                        {group.items.map((criterion) => (
                            <CriterionRow key={criterion.id} siteId={site.id} criterion={criterion} />
                        ))}
                    </ul>
                </section>
            ))}
        </SiteLayout>
    );
}

function CriterionRow({ siteId, criterion }: { siteId: number; criterion: Criterion }) {
    const [notesOpen, setNotesOpen] = useState(Boolean(criterion.notes));
    const [notes, setNotes] = useState(criterion.notes ?? '');
    const save = (status: ChecklistStatus, newNotes = notes) =>
        router.put(`/sites/${siteId}/checklist/${criterion.id}`, { status, notes: newNotes || null }, { preserveScroll: true, preserveState: true });
    const labelId = `criterion-${criterion.id}`;

    return (
        <li className="grid gap-3 px-5 py-4 md:grid-cols-[1fr_auto]">
            <div className="min-w-0">
                <p id={labelId} className="font-medium">
                    <span className="tabular-nums">{criterion.id}</span> {t(criterion.title)}{' '}
                    <span className="text-muted-foreground ml-1 rounded border px-1.5 py-0.5 text-xs font-medium">
                        {t('Level {level}', { level: criterion.level })}
                    </span>
                </p>
                <div className="text-muted-foreground mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    <a
                        href={criterion.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                    >
                        {t('Understanding {criterion}', { criterion: criterion.id })} <ExternalLink className="size-3" aria-hidden="true" />
                        <span className="sr-only">{t('(opens in a new tab)')}</span>
                    </a>
                    {criterion.automated_issues > 0 && (
                        <Link
                            href={`/sites/${siteId}/issues?criterion=${criterion.id}`}
                            className="inline-flex items-center gap-1 font-semibold text-red-700 underline-offset-4 hover:underline dark:text-red-400"
                        >
                            <Bot className="size-3.5" aria-hidden="true" />
                            {t('{count, plural, one {# open automated issue} other {# open automated issues}}', {
                                count: criterion.automated_issues,
                            })}
                        </Link>
                    )}
                    {criterion.tested_by && (
                        <span>{t('Reviewed by {person} {when}', { person: criterion.tested_by, when: relativeTime(criterion.tested_at) })}</span>
                    )}
                </div>
                {notesOpen ? (
                    <div className="mt-3 grid gap-2">
                        <label htmlFor={`notes-${criterion.id}`} className="text-xs font-medium">
                            {t('Notes and evidence')}
                        </label>
                        <textarea
                            id={`notes-${criterion.id}`}
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                            placeholder={t('How you tested, what you found, links to evidence')}
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            className="w-fit"
                            disabled={notes === (criterion.notes ?? '')}
                            onClick={() => save(criterion.status === 'not_tested' ? 'not_tested' : criterion.status, notes)}
                        >
                            {t('Save notes')}
                        </Button>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => setNotesOpen(true)}
                        className="text-muted-foreground hover:text-foreground mt-2 text-xs font-medium underline-offset-4 hover:underline"
                    >
                        {t('Add notes')}
                    </button>
                )}
            </div>
            <div role="group" aria-labelledby={labelId} className="flex flex-wrap gap-1 self-start">
                {statusOptions.map((option) => {
                    const checked = criterion.status === option.value;
                    return (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={checked}
                            onClick={() => !checked && save(option.value)}
                            className={cn('rounded-md border px-2.5 py-1 text-xs font-semibold', checked ? option.active : 'hover:bg-muted')}
                        >
                            {t(option.label)}
                        </button>
                    );
                })}
            </div>
        </li>
    );
}
