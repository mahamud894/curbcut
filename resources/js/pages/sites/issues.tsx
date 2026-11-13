import { ImpactBadge, impactOrder } from '@/components/curbcut/impact-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SiteLayout from '@/layouts/site-layout';
import { plural, shortUrl } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { IssueRow, Paginated, RuleGroup, SiteSummary, TeamMember } from '@/types/curbcut';
import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Filters {
    status: 'open' | 'fixed' | 'dismissed';
    impact?: string;
    rule?: string;
    page?: number;
    search?: string;
    assignee?: string;
    criterion?: string;
}

interface Props {
    site: SiteSummary;
    rules: RuleGroup[];
    issues: Paginated<IssueRow>;
    filters: Filters;
    pages: { id: number; url: string; title: string | null }[];
    team: TeamMember[];
    dismissReasons: Record<string, string>;
}

const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

export default function SiteIssues({ site, rules, issues, filters, pages, team }: Props) {
    const base = `/sites/${site.id}/issues`;
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<number[]>([]);

    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== undefined && v !== '' && v !== null));
        setSelected([]);
        router.get(base, query, { preserveScroll: true, preserveState: true });
    };

    const onSearch = (e: FormEvent) => {
        e.preventDefault();
        apply({ search: search || undefined, rule: undefined });
    };

    const bulk = (data: Record<string, unknown>) =>
        router.patch(base, { ids: selected, ...data } as RequestPayload, { preserveScroll: true, onSuccess: () => setSelected([]) });

    const pageIds = issues.data.map((i) => i.id);
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const canTriage = filters.status !== 'fixed';
    const activeRule = rules.find((r) => r.rule_id === filters.rule);

    return (
        <SiteLayout site={site} title="Issues">
            <div className="flex flex-wrap items-end gap-3">
                <div role="group" aria-label="Issue status" className="bg-muted inline-flex rounded-lg p-1">
                    {(['open', 'fixed', 'dismissed'] as const).map((s) => (
                        <button
                            key={s}
                            type="button"
                            aria-pressed={filters.status === s}
                            onClick={() => apply({ status: s, rule: undefined })}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium capitalize',
                                filters.status === s ? 'bg-background shadow-xs' : 'text-foreground/80 hover:text-foreground',
                            )}
                        >
                            {s}
                        </button>
                    ))}
                </div>
                <div className="grid gap-1">
                    <label htmlFor="assignee-filter" className="text-muted-foreground text-xs font-medium">
                        Assignee
                    </label>
                    <select
                        id="assignee-filter"
                        value={filters.assignee ?? ''}
                        onChange={(e) => apply({ assignee: e.target.value || undefined })}
                        className={selectClass}
                    >
                        <option value="">Anyone</option>
                        <option value="me">Assigned to me</option>
                        <option value="none">Unassigned</option>
                        <option value="overdue">Overdue</option>
                        {team.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <label htmlFor="impact-filter" className="text-muted-foreground text-xs font-medium">
                        Impact
                    </label>
                    <select
                        id="impact-filter"
                        value={filters.impact ?? ''}
                        onChange={(e) => apply({ impact: e.target.value || undefined, rule: undefined })}
                        className={cn(selectClass, 'capitalize')}
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
                        className={cn(selectClass, 'max-w-64')}
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

                <section aria-labelledby="list-heading" className="grid content-start gap-3">
                    <h2 id="list-heading" className="sr-only">
                        Issue list
                    </h2>

                    {filters.criterion && (
                        <p className="bg-muted flex flex-wrap items-center gap-3 rounded-xl px-4 py-2 text-sm">
                            Showing issues for WCAG {filters.criterion}
                            <button
                                type="button"
                                onClick={() => apply({ criterion: undefined })}
                                className="font-medium underline underline-offset-4"
                            >
                                Show all
                            </button>
                        </p>
                    )}

                    {activeRule && filters.status === 'open' && <RuleAssign siteId={site.id} rule={activeRule} team={team} />}

                    {issues.data.length === 0 ? (
                        <div className="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm">
                            <CheckCircle2 className="size-6 text-emerald-600" aria-hidden="true" />
                            {filters.status === 'open' ? 'No open issues match these filters.' : `No ${filters.status} issues match these filters.`}
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                {canTriage ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="size-4"
                                            checked={allSelected}
                                            onChange={() =>
                                                setSelected(
                                                    allSelected
                                                        ? selected.filter((id) => !pageIds.includes(id))
                                                        : [...new Set([...selected, ...pageIds])],
                                                )
                                            }
                                        />
                                        Select all on this page
                                    </label>
                                ) : (
                                    <span />
                                )}
                                <p className="text-muted-foreground text-sm" aria-live="polite">
                                    {issues.from}–{issues.to} of {issues.total.toLocaleString()} {filters.status} issues
                                </p>
                            </div>

                            {selected.length > 0 && (
                                <BulkBar count={selected.length} team={team} status={filters.status} onApply={bulk} onClear={() => setSelected([])} />
                            )}

                            <ul className="grid gap-2">
                                {issues.data.map((issue) => (
                                    <li key={issue.id} className="bg-card flex items-start gap-3 rounded-xl border p-4 shadow-xs">
                                        {canTriage && (
                                            <input
                                                type="checkbox"
                                                className="mt-1 size-4 shrink-0"
                                                aria-label={`Select: ${issue.help} on ${shortUrl(issue.page.url)}`}
                                                checked={selected.includes(issue.id)}
                                                onChange={(e) =>
                                                    setSelected(e.target.checked ? [...selected, issue.id] : selected.filter((id) => id !== issue.id))
                                                }
                                            />
                                        )}
                                        <ImpactBadge impact={issue.impact} className="mt-0.5 shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <Link href={`${base}/${issue.id}`} className="font-medium underline-offset-4 hover:underline">
                                                {issue.help}
                                            </Link>
                                            <p className="text-muted-foreground mt-0.5 truncate font-mono text-xs">{issue.target}</p>
                                            <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                                <span className="truncate">on {shortUrl(issue.page.url)}</span>
                                                {issue.wcag.length > 0 && <span>WCAG {issue.wcag.join(', ')}</span>}
                                                <span className="inline-flex items-center gap-1">
                                                    <UserRound className="size-3.5" aria-hidden="true" />
                                                    {issue.assignee?.name ?? 'Unassigned'}
                                                </span>
                                                {issue.due_on && (
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center gap-1',
                                                            issue.overdue && 'font-semibold text-red-700 dark:text-red-400',
                                                        )}
                                                    >
                                                        <CalendarClock className="size-3.5" aria-hidden="true" />
                                                        {issue.overdue ? 'Overdue ' : 'Due '}
                                                        {issue.due_on}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            {issues.last_page > 1 && (
                                <nav aria-label="Pagination" className="mt-2 flex flex-wrap gap-1">
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

function BulkBar({
    count,
    team,
    status,
    onApply,
    onClear,
}: {
    count: number;
    team: TeamMember[];
    status: Filters['status'];
    onApply: (data: Record<string, unknown>) => void;
    onClear: () => void;
}) {
    const [assignee, setAssignee] = useState('');
    const [due, setDue] = useState('');

    return (
        <div role="region" aria-label="Bulk actions" className="bg-muted flex flex-wrap items-end gap-3 rounded-xl p-3 text-sm">
            <p className="self-center font-medium">{plural(count, 'issue')} selected</p>
            <div className="grid gap-1">
                <label htmlFor="bulk-assignee" className="text-xs font-medium">
                    Assign to
                </label>
                <select id="bulk-assignee" value={assignee} onChange={(e) => setAssignee(e.target.value)} className={selectClass}>
                    <option value="">Choose…</option>
                    <option value="none">Nobody (unassign)</option>
                    {team.map((m) => (
                        <option key={m.id} value={m.id}>
                            {m.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1">
                <label htmlFor="bulk-due" className="text-xs font-medium">
                    Due date
                </label>
                <Input id="bulk-due" type="date" value={due} onChange={(e) => setDue(e.target.value)} className="h-9 w-40" />
            </div>
            <Button
                size="sm"
                disabled={!assignee && !due}
                onClick={() =>
                    onApply({
                        ...(assignee ? { assignee_id: assignee === 'none' ? null : Number(assignee) } : {}),
                        ...(due ? { due_on: due } : {}),
                    })
                }
            >
                Apply
            </Button>
            {status === 'dismissed' && (
                <Button size="sm" variant="outline" onClick={() => onApply({ restore: true })}>
                    Reopen
                </Button>
            )}
            <Button size="sm" variant="ghost" onClick={onClear}>
                Clear selection
            </Button>
        </div>
    );
}

function RuleAssign({ siteId, rule, team }: { siteId: number; rule: RuleGroup; team: TeamMember[] }) {
    const [assignee, setAssignee] = useState('');
    return (
        <div className="flex flex-wrap items-center gap-3 rounded-xl border border-dashed p-3 text-sm">
            <span>
                Assign all <strong>{plural(rule.total, 'issue')}</strong> of this problem to
            </span>
            <label htmlFor="rule-assignee" className="sr-only">
                Assignee for all issues of this problem
            </label>
            <select id="rule-assignee" value={assignee} onChange={(e) => setAssignee(e.target.value)} className={selectClass}>
                <option value="">Choose…</option>
                {team.map((m) => (
                    <option key={m.id} value={m.id}>
                        {m.name}
                    </option>
                ))}
            </select>
            <Button
                size="sm"
                variant="outline"
                disabled={!assignee}
                onClick={() =>
                    router.patch(`/sites/${siteId}/issues`, { rule_id: rule.rule_id, assignee_id: Number(assignee) }, { preserveScroll: true })
                }
            >
                Assign all
            </Button>
        </div>
    );
}
