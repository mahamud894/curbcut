import { formatDay, relativeTime } from '@/lib/format';
import { t, tk } from '@/lib/i18n';
import type { ActivityRow } from '@/types/curbcut';

const statusWords: Record<string, string> = {
    pass: tk('passing'),
    fail: tk('failing'),
    not_applicable: tk('not applicable'),
    not_tested: tk('not tested'),
};

/** Human-readable sentence for an audit-trail entry. */
export function activitySentence(a: ActivityRow): string {
    const who = a.user ?? t('Someone');
    const p = a.properties ?? {};
    switch (a.action) {
        case 'issue.assigned':
            return p.assignee
                ? t('{who} assigned “{issue}” to {assignee}', { who, issue: p.issue, assignee: p.assignee })
                : t('{who} unassigned “{issue}”', { who, issue: p.issue });
        case 'issue.due_date_changed':
            return p.due_on
                ? t('{who} set the due date of “{issue}” to {date}', { who, issue: p.issue, date: formatDay(p.due_on) })
                : t('{who} removed the due date of “{issue}”', { who, issue: p.issue });
        case 'issue.dismissed':
            return t('{who} dismissed “{issue}”: {reason}', { who, issue: p.issue, reason: t(String(p.reason)) });
        case 'issue.restored':
            return t('{who} reopened “{issue}”', { who, issue: p.issue });
        case 'issue.commented':
            return t('{who} commented on “{issue}”', { who, issue: p.issue });
        case 'checklist.updated':
            return t('{who} marked {criterion} {title} as {status}', {
                who,
                criterion: p.criterion,
                title: t(String(p.title)),
                status: t(statusWords[p.to ?? ''] ?? String(p.to)),
            });
        case 'statement.updated':
            return t('{who} edited the accessibility statement', { who });
        case 'statement.published':
            return t('{who} published the accessibility statement', { who });
        case 'statement.unpublished':
            return t('{who} unpublished the accessibility statement', { who });
        case 'member.added':
            return t('{who} gave {member} access', { who, member: p.member });
        case 'report.generated':
            return t('{who} downloaded an audit report', { who });
        case 'member.removed':
            return t('{who} removed {member}', { who, member: p.member });
        default:
            return `${who}: ${a.action}`;
    }
}

export function ActivityList({ items, empty }: { items: ActivityRow[]; empty?: string }) {
    if (items.length === 0) return <p className="text-muted-foreground text-sm">{empty ?? t('No activity yet.')}</p>;
    return (
        <ol className="grid gap-3">
            {items.map((a) => (
                <li key={a.id} className="flex gap-3 text-sm">
                    <span className="bg-muted-foreground/60 mt-1.5 size-1.5 shrink-0 rounded-full" aria-hidden="true" />
                    <span>
                        {activitySentence(a)}
                        <span className="text-muted-foreground block text-xs">
                            <time dateTime={a.created_at ?? undefined}>{relativeTime(a.created_at)}</time>
                        </span>
                    </span>
                </li>
            ))}
        </ol>
    );
}
