import { ImpactBadge } from '@/components/curbcut/impact-badge';
import { Stat } from '@/components/curbcut/stat';
import SiteLayout from '@/layouts/site-layout';
import { formatDate, shortUrl } from '@/lib/format';
import type { Impact, ScanSummary, SiteSummary } from '@/types/curbcut';

interface Row {
    id: number;
    rule_id: string;
    impact: Impact;
    help: string;
    target: string;
    page_url: string;
}

interface Props {
    site: SiteSummary;
    scan: ScanSummary;
    newIssues: Row[];
    fixedIssues: Row[];
    failedPages: { url: string; error: string | null; http_status: number | null }[];
}

export default function ScanDetail({ site, scan, newIssues, fixedIssues, failedPages }: Props) {
    const duration =
        scan.started_at && scan.finished_at ? Math.round((new Date(scan.finished_at).getTime() - new Date(scan.started_at).getTime()) / 1000) : null;

    return (
        <SiteLayout site={site} title={`Scan #${scan.id}`}>
            <div>
                <h2 className="text-lg font-semibold">Scan #{scan.id}</h2>
                <p className="text-muted-foreground text-sm">
                    {formatDate(scan.started_at ?? scan.created_at)} · {scan.trigger === 'scheduled' ? 'Scheduled' : 'Started manually'} ·{' '}
                    <span className="capitalize">{scan.status}</span>
                    {duration !== null && ` · took ${duration}s`}
                </p>
                {scan.error && <p className="mt-2 text-sm text-red-700 dark:text-red-400">{scan.error}</p>}
            </div>

            <section aria-label="Scan totals" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Score" value={scan.score ?? '–'} />
                <Stat label="Pages scanned" value={scan.pages_scanned} hint={scan.pages_failed ? `${scan.pages_failed} failed` : undefined} />
                <Stat label="New issues" value={scan.issues_new} tone={scan.issues_new ? 'text-red-700 dark:text-red-400' : undefined} />
                <Stat
                    label="Fixed issues"
                    value={scan.issues_fixed}
                    tone={scan.issues_fixed ? 'text-emerald-700 dark:text-emerald-400' : undefined}
                />
            </section>

            <IssueTable title="New in this scan" rows={newIssues} empty="Nothing new appeared in this scan." />
            <IssueTable title="Fixed in this scan" rows={fixedIssues} empty="Nothing was fixed in this scan." />

            {failedPages.length > 0 && (
                <section aria-labelledby="failed-heading" className="bg-card rounded-xl border shadow-xs">
                    <h2 id="failed-heading" className="p-5 pb-3 font-semibold">
                        Pages that could not be scanned
                    </h2>
                    <ul className="divide-y border-t text-sm">
                        {failedPages.map((p) => (
                            <li key={p.url} className="px-5 py-3">
                                <span className="font-medium">{p.url}</span>
                                <span className="text-muted-foreground block text-xs">{p.error}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </SiteLayout>
    );
}

function IssueTable({ title, rows, empty }: { title: string; rows: Row[]; empty: string }) {
    const id = title.toLowerCase().replace(/\W+/g, '-');
    return (
        <section aria-labelledby={id} className="bg-card rounded-xl border shadow-xs">
            <h2 id={id} className="p-5 pb-3 font-semibold">
                {title} <span className="text-muted-foreground font-normal">({rows.length})</span>
            </h2>
            {rows.length === 0 ? (
                <p className="text-muted-foreground px-5 pb-5 text-sm">{empty}</p>
            ) : (
                <ul className="divide-y border-t">
                    {rows.map((r) => (
                        <li key={r.id} className="flex items-start gap-3 px-5 py-3 text-sm">
                            <ImpactBadge impact={r.impact} className="mt-0.5 shrink-0" />
                            <span className="min-w-0">
                                <span className="block">{r.help}</span>
                                <span className="text-muted-foreground block truncate font-mono text-xs">
                                    {shortUrl(r.page_url)} · {r.target}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
