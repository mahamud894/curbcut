import { relativeTime } from '@/lib/format';
import type { ActivityRow } from '@/types/curbcut';

const statusWords: Record<string, string> = { pass: 'passing', fail: 'failing', not_applicable: 'not applicable', not_tested: 'not tested' };

/** Human-readable sentence for an audit-trail entry. */
export function activitySentence(a: ActivityRow): string {
    const who = a.user ?? 'Someone';
    const p = a.properties ?? {};
    switch (a.action) {
        case 'issue.assigned':
            return p.assignee ? `${who} assigned “${p.issue}” to ${p.assignee}` : `${who} unassigned “${p.issue}”`;
        case 'issue.due_date_changed':
            return p.due_on ? `${who} set the due date of “${p.issue}” to ${p.due_on}` : `${who} removed the due date of “${p.issue}”`;
        case 'issue.dismissed':
            return `${who} dismissed “${p.issue}” as ${String(p.reason).toLowerCase()}`;
        case 'issue.restored':
            return `${who} reopened “${p.issue}”`;
        case 'issue.commented':
            return `${who} commented on “${p.issue}”`;
        case 'checklist.updated':
            return `${who} marked ${p.criterion} ${p.title} as ${statusWords[p.to ?? ''] ?? p.to}`;
        case 'statement.updated':
            return `${who} edited the accessibility statement`;
        case 'statement.published':
            return `${who} published the accessibility statement`;
        case 'statement.unpublished':
            return `${who} unpublished the accessibility statement`;
        case 'member.added':
            return `${who} gave ${p.member} access`;
        case 'report.generated':
            return `${who} downloaded an audit report`;
        case 'member.removed':
            return `${who} removed ${p.member}`;
        default:
            return `${who}: ${a.action}`;
    }
}

export function ActivityList({ items, empty = 'No activity yet.' }: { items: ActivityRow[]; empty?: string }) {
    if (items.length === 0) return <p className="text-muted-foreground text-sm">{empty}</p>;
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
