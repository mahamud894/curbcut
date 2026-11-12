import { ImpactBadge, impactOrder } from '@/components/curbcut/impact-badge';
import { Input } from '@/components/ui/input';
import SiteLayout from '@/layouts/site-layout';
import { plural, shortUrl } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { IssueRow, Paginated, RuleGroup, SiteSummary } from '@/types/curbcut';
import { Link, router } from '@inertiajs/react';
import { BookOpen, CheckCircle2, ChevronDown, ExternalLink } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Filters {
    status: 'open' | 'fixed';
    impact?: string;
    rule?: string;
    page?: number;
    search?: string;
}

interface Props {
    site: SiteSummary;
    rules: RuleGroup[];
    issues: Paginated<IssueRow>;
    filters: Filters;
    pages: { id: number; url: string; title: string | null }[];
}

export default function SiteIssues({ site, rules, issues, filters, pages }: Props) {
    const base = `/sites/${site.id}/issues`;
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== undefined && v !== '' && v !== null));
        router.get(base, query, { preserveScroll: true, preserveState: true });
    };

    const onSearch = (e: FormEvent) => {
        e.preventDefault();
        apply({ search: search || undefined, rule: undefined });
    };

    return (
        <SiteLayout site={site} title="Issues">
            <div className="flex flex-wrap items-end gap-3">
                <div role="group" aria-label="Issue status" className="bg-muted inline-flex rounded-lg p-1">
                    {(['open', 'fixed'] as const).map((s) => (
                        <button
                            key={s}
                            type="button"
                            aria-pressed={filters.status === s}
                            onClick={() => apply({ status: s, rule: undefined })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium capitalize',
                                filters.status === s ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {s}
                        </button>
                    ))}
                </div>
                <div className="grid gap-1">
                    <label htmlFor="impact-filter" className="text-muted-foreground text-xs font-medium">
                        Impact
                    </label>
                    <select
                        id="impact-filter"
                        value={filters.impact ?? ''}
                        onChange={(e) => apply({ impact: e.target.value || undefined, rule: undefined })}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm capitalize"
                    >
                        <option value="">All impacts</option>
                        {impactOrder.map((i) => (
                            <option key={i} value={i}>
                                {i}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <label htmlFor="page-filter" className="text-muted-foreground text-xs font-medium">
                        Page
                    </label>
                    <select
                        id="page-filter"
                        value={filters.page ?? ''}
                        onChange={(e) => apply({ page: e.target.value ? Number(e.target.value) : undefined, rule: undefined })}
                        className="border-input bg-background h-9 max-w-64 rounded-md border px-3 text-sm"
                    >
                        <option value="">All pages</option>
                        {pages.map((p) => (
                            <option key={p.id} value={p.id}>
                                {shortUrl(p.url)}
                            </option>
                        ))}
                    </select>
                </div>
                <form onSubmit={onSearch} className="grid gap-1" role="search">
                    <label htmlFor="issue-search" className="text-muted-foreground text-xs font-medium">
                        Search
                    </label>
                    <Input
                        id="issue-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rule, selector…"
                        className="w-56"
                    />
                </form>
            </div>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,340px)_1fr]">
                <section aria-labelledby="rules-heading" className="bg-card self-start rounded-xl border shadow-xs">
                    <h2 id="rules-heading" className="border-b px-4 py-3 text-sm font-semibold">
                        Problems ({rules.length})
                    </h2>
                    <ul className="max-h-[70vh] overflow-y-auto p-1">
                        <li>
                            <button
                                type="button"
                                onClick={() => apply({ rule: undefined })}
                                aria-pressed={!filters.rule}
                                className={cn(
                                    'w-full rounded-md px-3 py-2 text-left text-sm',
                                    !filters.rule ? 'bg-muted font-semibold' : 'hover:bg-muted/60',
                                )}
                            >
                                All problems
                            </button>
                        </li>
                        {rules.map((rule) => (
                            <li key={rule.rule_id}>
                                <button
                                    type="button"
                                    onClick={() => apply({ rule: rule.rule_id })}
                                    aria-pressed={filters.rule === rule.rule_id}
                                    className={cn(
                                        'flex w-full items-start gap-2 rounded-md px-3 py-2 text-left text-sm',
                                        filters.rule === rule.rule_id ? 'bg-muted' : 'hover:bg-muted/60',
                                    )}
                                >
                                    <ImpactBadge impact={rule.impact} className="mt-0.5 shrink-0" />
                                    <span className="min-w-0 flex-1">
                                        <span className="block">{rule.help}</span>
                                        <span className="text-muted-foreground text-xs">
                                            {plural(rule.total, 'element')} on {plural(rule.pages, 'page')}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>

                <section aria-labelledby="list-heading">
                    <h2 id="list-heading" className="sr-only">
                        Issue list
                    </h2>
                    {issues.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm">
                            <CheckCircle2 className="size-6 text-emerald-600" aria-hidden="true" />
                            {filters.status === 'open' ? 'No open issues match these filters.' : 'No fixed issues yet.'}
                        </div>
                    ) : (
                        <>
                            <p className="text-muted-foreground mb-3 text-sm" aria-live="polite">
                                Showing {issues.from}–{issues.to} of {issues.total.toLocaleString()} {filters.status} issues
                            </p>
                            <ul className="grid gap-3">
                                {issues.data.map((issue) => (
                                    <IssueCard key={issue.id} issue={issue} />
                                ))}
                            </ul>
                            {issues.last_page > 1 && (
                                <nav aria-label="Pagination" className="mt-4 flex flex-wrap gap-1">
                                    {issues.links.map((link, i) =>
                                        link.url ? (
                                            <Link
                                                key={i}
                                                href={link.url}
                                                preserveScroll
                                                aria-current={link.active ? 'page' : undefined}
                                                className={cn(
                                                    'rounded-md border px-3 py-1.5 text-sm',
                                                    link.active ? 'bg-foreground text-background' : 'hover:bg-muted',
                                                )}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : (
                                            <span
                                                key={i}
                                                className="text-muted-foreground rounded-md border px-3 py-1.5 text-sm opacity-50"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ),
                                    )}
                                </nav>
                            )}
                        </>
                    )}
                </section>
            </div>
        </SiteLayout>
    );
}

function IssueCard({ issue }: { issue: IssueRow }) {
    const [open, setOpen] = useState(false);
    const detailsId = `issue-${issue.id}-details`;

    return (
        <li className="bg-card rounded-xl border shadow-xs">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                aria-controls={detailsId}
                className="flex w-full items-start gap-3 p-4 text-left"
            >
                <ImpactBadge impact={issue.impact} className="mt-0.5 shrink-0" />
                <span className="min-w-0 flex-1">
                    <span className="block font-medium">{issue.help}</span>
                    <span className="text-muted-foreground mt-0.5 block truncate font-mono text-xs">{issue.target}</span>
                    <span className="text-muted-foreground mt-1 block truncate text-xs">on {shortUrl(issue.page.url)}</span>
                </span>
                <ChevronDown
                    className={cn('text-muted-foreground mt-1 size-4 shrink-0 transition-transform', open && 'rotate-180')}
                    aria-hidden="true"
                />
            </button>
            {open && (
                <div id={detailsId} className="grid gap-3 border-t p-4 text-sm">
                    {issue.description && <p>{issue.description}</p>}
                    {issue.html && (
                        <div>
                            <p className="text-muted-foreground mb-1 text-xs font-medium uppercase">Element</p>
                            <pre className="bg-muted overflow-x-auto rounded-md p-3 font-mono text-xs whitespace-pre-wrap">{issue.html}</pre>
                        </div>
                    )}
                    {issue.failure_summary && (
                        <div>
                            <p className="text-muted-foreground mb-1 text-xs font-medium uppercase">How to fix</p>
                            <p className="whitespace-pre-line">{issue.failure_summary}</p>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center gap-3 text-xs">
                        {issue.wcag.length > 0 && <span className="bg-muted rounded px-2 py-1 font-medium">WCAG {issue.wcag.join(', ')}</span>}
                        <span className="text-muted-foreground font-mono">{issue.rule_id}</span>
                        {issue.help_url && (
                            <a
                                href={issue.help_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                            >
                                <BookOpen className="size-3.5" aria-hidden="true" /> Learn how to fix
                                <span className="sr-only">(opens in a new tab)</span>
                            </a>
                        )}
                        <a
                            href={issue.page.url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                        >
                            <ExternalLink className="size-3.5" aria-hidden="true" /> Open page
                            <span className="sr-only">(opens in a new tab)</span>
                        </a>
                    </div>
                </div>
            )}
        </li>
    );
}
