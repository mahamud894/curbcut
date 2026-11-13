import { ActivityList } from '@/components/curbcut/activity-text';
import { ImpactBadge } from '@/components/curbcut/impact-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SiteLayout from '@/layouts/site-layout';
import { formatDate, relativeTime } from '@/lib/format';
import type { ActivityRow, IssueRow, SiteSummary, TeamMember } from '@/types/curbcut';
import type { RequestPayload } from '@inertiajs/core';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, CalendarClock, ExternalLink, EyeOff, RotateCcw } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface IssueDetail extends IssueRow {
    assignee: TeamMember | null;
    due_on: string | null;
    overdue: boolean;
    dismissed_reason: string | null;
    dismissed_note: string | null;
    dismissed_by: string | null;
    dismissed_at: string | null;
    criteria: { id: string; title: string; url: string }[];
    comments: { id: number; body: string; user: string | null; created_at: string | null }[];
}

interface Props {
    site: SiteSummary;
    issue: IssueDetail;
    activity: ActivityRow[];
    team: TeamMember[];
    dismissReasons: Record<string, string>;
}

const selectClass = 'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function IssueShow({ site, issue, activity, team, dismissReasons }: Props) {
    const base = `/sites/${site.id}/issues/${issue.id}`;
    const update = (data: RequestPayload) => router.patch(base, data, { preserveScroll: true });

    return (
        <SiteLayout site={site} title={issue.help}>
            <Link
                href={`/sites/${site.id}/issues`}
                className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1 text-sm"
            >
                <ArrowLeft className="size-4" aria-hidden="true" /> All issues
            </Link>

            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="grid content-start gap-6">
                    <section aria-labelledby="issue-title" className="bg-card rounded-xl border p-5 shadow-xs">
                        <div className="flex flex-wrap items-center gap-2">
                            <ImpactBadge impact={issue.impact} />
                            <StatusPill issue={issue} />
                            {issue.overdue && (
                                <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-950 dark:text-red-200">
                                    Overdue
                                </span>
                            )}
                        </div>
                        <h2 id="issue-title" className="mt-3 text-xl font-semibold">
                            {issue.help}
                        </h2>
                        {issue.description && <p className="text-muted-foreground mt-1">{issue.description}</p>}

                        <dl className="mt-5 grid gap-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground text-xs font-medium uppercase">Page</dt>
                                <dd>
                                    <a
                                        href={issue.page.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                                    >
                                        {issue.page.title || issue.page.url} <ExternalLink className="size-3.5" aria-hidden="true" />
                                        <span className="sr-only">(opens in a new tab)</span>
                                    </a>
                                    <span className="text-muted-foreground block text-xs">{issue.page.url}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs font-medium uppercase">Element</dt>
                                <dd className="font-mono text-xs break-all">{issue.target}</dd>
                            </div>
                            {issue.html && (
                                <div>
                                    <dt className="text-muted-foreground text-xs font-medium uppercase">HTML</dt>
                                    <dd>
                                        <pre className="bg-muted mt-1 overflow-x-auto rounded-md p-3 font-mono text-xs whitespace-pre-wrap">
                                            {issue.html}
                                        </pre>
                                    </dd>
                                </div>
                            )}
                            {issue.failure_summary && (
                                <div>
                                    <dt className="text-muted-foreground text-xs font-medium uppercase">How to fix</dt>
                                    <dd className="whitespace-pre-line">{issue.failure_summary}</dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-muted-foreground text-xs font-medium uppercase">WCAG success criteria</dt>
                                <dd className="flex flex-wrap gap-2">
                                    {issue.criteria.length === 0 ? (
                                        <span className="text-muted-foreground">Best practice (not a WCAG requirement)</span>
                                    ) : (
                                        issue.criteria.map((c) => (
                                            <a
                                                key={c.id}
                                                href={c.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="bg-muted rounded px-2 py-1 text-xs font-medium underline-offset-4 hover:underline"
                                            >
                                                {c.id} {c.title}
                                                <span className="sr-only"> (opens in a new tab)</span>
                                            </a>
                                        ))
                                    )}
                                </dd>
                            </div>
                        </dl>

                        {issue.help_url && (
                            <a
                                href={issue.help_url}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-5 inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                            >
                                <BookOpen className="size-4" aria-hidden="true" /> Detailed guidance from Deque
                                <span className="sr-only">(opens in a new tab)</span>
                            </a>
                        )}
                    </section>

                    <Comments base={base} comments={issue.comments} />
                </div>

                <aside aria-label="Issue workflow" className="grid content-start gap-4">
                    <section aria-labelledby="triage-heading" className="bg-card grid gap-4 rounded-xl border p-5 shadow-xs">
                        <h2 id="triage-heading" className="font-semibold">
                            Workflow
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="assignee">Assignee</Label>
                            <select
                                id="assignee"
                                className={selectClass}
                                value={issue.assignee?.id ?? ''}
                                onChange={(e) => update({ assignee_id: e.target.value ? Number(e.target.value) : null })}
                            >
                                <option value="">Unassigned</option>
                                {team.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="due_on">Due date</Label>
                            <Input
                                id="due_on"
                                type="date"
                                defaultValue={issue.due_on ?? ''}
                                onBlur={(e) => e.target.value !== (issue.due_on ?? '') && update({ due_on: e.target.value || null })}
                            />
                            {issue.due_on && (
                                <p className="text-muted-foreground flex items-center gap-1 text-xs">
                                    <CalendarClock className="size-3.5" aria-hidden="true" /> {issue.overdue ? 'Overdue since' : 'Due'} {issue.due_on}
                                </p>
                            )}
                        </div>
                        <DismissControl
                            issue={issue}
                            reasons={dismissReasons}
                            onDismiss={(reason, note) => update({ dismiss: { reason, note } })}
                            onRestore={() => update({ restore: true })}
                        />
                    </section>

                    <section aria-labelledby="history-heading" className="bg-card rounded-xl border p-5 shadow-xs">
                        <h2 id="history-heading" className="mb-3 font-semibold">
                            History
                        </h2>
                        <ul className="text-muted-foreground mb-4 grid gap-1 text-xs">
                            {issue.first_seen_scan_id && (
                                <li>
                                    First found in{' '}
                                    <Link className="underline-offset-4 hover:underline" href={`/sites/${site.id}/scans/${issue.first_seen_scan_id}`}>
                                        scan #{issue.first_seen_scan_id}
                                    </Link>
                                </li>
                            )}
                            {issue.fixed_in_scan_id && (
                                <li>
                                    Fixed in{' '}
                                    <Link className="underline-offset-4 hover:underline" href={`/sites/${site.id}/scans/${issue.fixed_in_scan_id}`}>
                                        scan #{issue.fixed_in_scan_id}
                                    </Link>
                                </li>
                            )}
                        </ul>
                        <ActivityList items={activity} empty="No changes yet." />
                    </section>
                </aside>
            </div>
        </SiteLayout>
    );
}

function StatusPill({ issue }: { issue: IssueDetail }) {
    const styles = {
        open: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
        fixed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
        dismissed: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
    return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold capitalize ${styles[issue.status]}`}>{issue.status}</span>;
}

function DismissControl({
    issue,
    reasons,
    onDismiss,
    onRestore,
}: {
    issue: IssueDetail;
    reasons: Record<string, string>;
    onDismiss: (reason: string, note: string) => void;
    onRestore: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState(Object.keys(reasons)[0]);
    const [note, setNote] = useState('');

    if (issue.status === 'fixed') return null;

    if (issue.status === 'dismissed') {
        return (
            <div className="bg-muted grid gap-2 rounded-lg p-3 text-sm">
                <p>
                    <span className="font-medium">Dismissed as {reasons[issue.dismissed_reason ?? '']?.toLowerCase() ?? issue.dismissed_reason}</span>
                    {issue.dismissed_by && ` by ${issue.dismissed_by}`} {issue.dismissed_at && relativeTime(issue.dismissed_at)}.
                </p>
                {issue.dismissed_note && <p className="text-muted-foreground">“{issue.dismissed_note}”</p>}
                <p className="text-muted-foreground text-xs">Dismissed issues don't count towards the score.</p>
                <Button variant="outline" size="sm" onClick={onRestore} className="w-fit">
                    <RotateCcw aria-hidden="true" /> Reopen
                </Button>
            </div>
        );
    }

    if (!open) {
        return (
            <Button variant="outline" size="sm" onClick={() => setOpen(true)} className="w-fit">
                <EyeOff aria-hidden="true" /> Dismiss…
            </Button>
        );
    }

    return (
        <form
            className="grid gap-3 rounded-lg border p-3"
            onSubmit={(e) => {
                e.preventDefault();
                onDismiss(reason, note);
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="dismiss-reason">Reason</Label>
                <select id="dismiss-reason" className={selectClass} value={reason} onChange={(e) => setReason(e.target.value)}>
                    {Object.entries(reasons).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="dismiss-note">Justification</Label>
                <textarea
                    id="dismiss-note"
                    rows={3}
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                    placeholder="Why this does not need fixing"
                />
                <p className="text-muted-foreground text-xs">Kept in the audit trail.</p>
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm">
                    Dismiss issue
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setOpen(false)}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function Comments({ base, comments }: { base: string; comments: IssueDetail['comments'] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`${base}/comments`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section aria-labelledby="comments-heading" className="bg-card rounded-xl border p-5 shadow-xs">
            <h2 id="comments-heading" className="mb-4 font-semibold">
                Comments ({comments.length})
            </h2>
            {comments.length > 0 && (
                <ol className="mb-5 grid gap-4">
                    {comments.map((c) => (
                        <li key={c.id} className="text-sm">
                            <p>
                                <span className="font-semibold">{c.user ?? 'Former member'}</span>{' '}
                                <time className="text-muted-foreground text-xs" dateTime={c.created_at ?? undefined}>
                                    {formatDate(c.created_at)}
                                </time>
                            </p>
                            <p className="mt-1 whitespace-pre-line">{c.body}</p>
                        </li>
                    ))}
                </ol>
            )}
            <form onSubmit={submit} className="grid gap-2">
                <Label htmlFor="comment-body">Add a comment</Label>
                <textarea
                    id="comment-body"
                    rows={3}
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    className="border-input bg-background rounded-md border px-3 py-2 text-sm"
                />
                <InputError message={errors.body} />
                <Button type="submit" size="sm" disabled={processing || !data.body.trim()} className="w-fit">
                    Comment
                </Button>
            </form>
        </section>
    );
}
