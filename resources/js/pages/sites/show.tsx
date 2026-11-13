import { ActivityList } from '@/components/curbcut/activity-text';
import { ImpactBadge, impactDot, impactOrder } from '@/components/curbcut/impact-badge';
import { ScoreRing, scoreTone } from '@/components/curbcut/score-ring';
import { Stat } from '@/components/curbcut/stat';
import SiteLayout from '@/layouts/site-layout';
import { capitalize, formatDate, formatDay, formatNumber } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ActivityRow, RuleGroup, ScanSummary, SiteSummary } from '@/types/curbcut';
import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, CheckCircle2, FileWarning } from 'lucide-react';

interface Props {
    site: SiteSummary;
    history: { id: number; date: string | null; score: number | null; issues_open: number }[];
    topRules: RuleGroup[];
    recentScans: ScanSummary[];
    activity: ActivityRow[];
    myOpenIssues: number;
}

export default function SiteShow({ site, history, topRules, recentScans, activity, myOpenIssues }: Props) {
    const scan = site.latest_completed_scan;
    const failed = site.latest_scan?.status === 'failed' ? site.latest_scan : null;

    return (
        <SiteLayout site={site} title={t('Overview')}>
            {failed && (
                <div
                    role="alert"
                    className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                >
                    <FileWarning className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                        <p className="font-semibold">{t('The last scan failed.')}</p>
                        <p>{failed.error}</p>
                    </div>
                </div>
            )}

            {myOpenIssues > 0 && (
                <Link
                    href={`/sites/${site.id}/issues?assignee=me`}
                    className="w-fit rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900 hover:underline dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100"
                >
                    {t('{count, plural, one {# open issue} other {# open issues}} assigned to you', { count: myOpenIssues })} →
                </Link>
            )}

            {!scan ? (
                <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                    {t('Results appear here when the first scan finishes.')}
                </div>
            ) : (
                <>
                    <section aria-label={t('Latest scan')} className="grid gap-4 lg:grid-cols-[minmax(0,320px)_1fr]">
                        <div className="bg-card flex items-center gap-5 rounded-xl border p-5 shadow-xs">
                            <ScoreRing score={scan.score} size={112} />
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-sm font-medium">{t('Accessibility score')}</p>
                                <p className={cn('text-lg font-semibold break-words hyphens-auto', scoreTone(scan.score).text)}>
                                    {t(scoreTone(scan.score).label)}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {t('{count, plural, one {# page} other {# pages}} scanned {when}', {
                                        count: scan.pages_scanned,
                                        when: formatDate(scan.finished_at),
                                    })}
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Stat
                                label={t('Open issues')}
                                value={formatNumber(scan.issues_open)}
                                hint={t('{critical} critical · {serious} serious', { critical: scan.critical, serious: scan.serious })}
                            />
                            <Stat
                                label={t('New in last scan')}
                                value={formatNumber(scan.issues_new)}
                                tone={scan.issues_new > 0 ? 'text-red-700 dark:text-red-400' : undefined}
                                icon={<ArrowUpRight className="size-4" aria-hidden="true" />}
                            />
                            <Stat
                                label={t('Fixed in last scan')}
                                value={formatNumber(scan.issues_fixed)}
                                tone={scan.issues_fixed > 0 ? 'text-emerald-700 dark:text-emerald-400' : undefined}
                                icon={<ArrowDownRight className="size-4" aria-hidden="true" />}
                            />
                        </div>
                    </section>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <section aria-labelledby="impact-heading" className="bg-card rounded-xl border p-5 shadow-xs">
                            <h2 id="impact-heading" className="font-semibold">
                                {t('Open issues by impact')}
                            </h2>
                            <ImpactBar scan={scan} />
                            <ul className="mt-4 grid grid-cols-2 gap-2 text-sm">
                                {impactOrder.map((impact) => (
                                    <li key={impact}>
                                        <Link
                                            href={`/sites/${site.id}/issues?impact=${impact}`}
                                            className="hover:bg-muted flex items-center justify-between rounded-md px-2 py-1.5"
                                        >
                                            <span className="flex items-center gap-2 capitalize">
                                                <span className={cn('size-2.5 rounded-full', impactDot[impact])} aria-hidden="true" />
                                                {t(impact)}
                                            </span>
                                            <span className="font-semibold tabular-nums">{scan[impact]}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>

                        <section aria-labelledby="trend-heading" className="bg-card rounded-xl border p-5 shadow-xs">
                            <h2 id="trend-heading" className="font-semibold">
                                {t('Score over time')}
                            </h2>
                            <ScoreTrend history={history} />
                        </section>
                    </div>

                    <section aria-labelledby="rules-heading" className="bg-card rounded-xl border shadow-xs">
                        <div className="flex items-center justify-between p-5 pb-3">
                            <h2 id="rules-heading" className="font-semibold">
                                {t('Most common problems')}
                            </h2>
                            <Link href={`/sites/${site.id}/issues`} className="text-sm font-medium underline-offset-4 hover:underline">
                                {t('All issues')}
                            </Link>
                        </div>
                        {topRules.length === 0 ? (
                            <p className="flex items-center gap-2 px-5 pb-5 text-sm text-emerald-700 dark:text-emerald-400">
                                <CheckCircle2 className="size-4" aria-hidden="true" /> {t('No automatically detectable issues. Nice work!')}
                            </p>
                        ) : (
                            <ul className="divide-y border-t">
                                {topRules.map((rule) => (
                                    <li key={rule.rule_id}>
                                        <Link
                                            href={`/sites/${site.id}/issues?rule=${rule.rule_id}`}
                                            className="hover:bg-muted/50 flex items-center gap-4 px-5 py-3"
                                        >
                                            <ImpactBadge impact={rule.impact} className="w-20 justify-center" />
                                            <span className="min-w-0 flex-1 truncate text-sm">{rule.help}</span>
                                            <span className="text-muted-foreground text-sm whitespace-nowrap tabular-nums">
                                                {t(
                                                    '{elements, plural, one {# element} other {# elements}} · {pages, plural, one {# page} other {# pages}}',
                                                    { elements: rule.total, pages: rule.pages },
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </>
            )}

            <div className="grid gap-4 lg:grid-cols-[1fr_minmax(0,380px)]">
                <section aria-labelledby="scans-heading" className="bg-card rounded-xl border shadow-xs">
                    <h2 id="scans-heading" className="p-5 pb-3 font-semibold">
                        {t('Scan history')}
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground border-y text-left text-xs uppercase">
                                <tr>
                                    <th scope="col" className="px-5 py-2 font-medium">
                                        {t('Started')}
                                    </th>
                                    <th scope="col" className="px-5 py-2 font-medium">
                                        {t('Status')}
                                    </th>
                                    <th scope="col" className="px-5 py-2 text-right font-medium">
                                        {t('Pages')}
                                    </th>
                                    <th scope="col" className="px-5 py-2 text-right font-medium">
                                        {t('Score')}
                                    </th>
                                    <th scope="col" className="px-5 py-2 text-right font-medium">
                                        {t('New')}
                                    </th>
                                    <th scope="col" className="px-5 py-2 text-right font-medium">
                                        {t('Fixed')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {recentScans.map((s) => (
                                    <tr key={s.id} className="hover:bg-muted/50">
                                        <td className="px-5 py-2.5">
                                            <Link href={`/sites/${site.id}/scans/${s.id}`} className="font-medium underline-offset-4 hover:underline">
                                                {formatDate(s.started_at ?? s.created_at)}
                                            </Link>
                                            {s.trigger !== 'manual' && (
                                                <span className="text-muted-foreground ml-2 text-xs">
                                                    {s.trigger === 'api' ? 'API' : t('scheduled')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-2.5">{t(capitalize(s.status))}</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{s.pages_scanned}</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{s.score ?? '–'}</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{s.issues_new}</td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{s.issues_fixed}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <section aria-labelledby="activity-heading" className="bg-card rounded-xl border p-5 shadow-xs">
                    <h2 id="activity-heading" className="mb-3 font-semibold">
                        {t('Recent activity')}
                    </h2>
                    <ActivityList items={activity} empty={t('Assignments, checklist results and statement changes appear here.')} />
                </section>
            </div>
        </SiteLayout>
    );
}

function ImpactBar({ scan }: { scan: ScanSummary }) {
    const total = impactOrder.reduce((sum, i) => sum + scan[i], 0);
    if (total === 0) return <div className="bg-muted mt-4 h-3 rounded-full" aria-hidden="true" />;
    return (
        <div className="mt-4 flex h-3 overflow-hidden rounded-full" aria-hidden="true">
            {impactOrder.map((impact) =>
                scan[impact] > 0 ? <div key={impact} className={impactDot[impact]} style={{ width: `${(scan[impact] / total) * 100}%` }} /> : null,
            )}
        </div>
    );
}

function ScoreTrend({ history }: { history: Props['history'] }) {
    const points = history.filter((h) => h.score !== null);
    if (points.length < 2) {
        return <p className="text-muted-foreground mt-4 text-sm">{t('The trend appears after the second completed scan.')}</p>;
    }
    const w = 400;
    const h = 120;
    const step = w / (points.length - 1);
    // Zoom in on the range the scores actually cover, so small improvements are visible.
    const values = points.map((p) => p.score!);
    const lo = Math.max(0, Math.min(...values) - 10);
    const hi = Math.min(100, Math.max(...values) + 5);
    const y = (score: number) => h - ((score - lo) / Math.max(1, hi - lo)) * (h - 10) - 5;
    const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${(i * step).toFixed(1)},${y(p.score!).toFixed(1)}`).join(' ');
    const first = points[0].score!;
    const last = points[points.length - 1].score!;

    return (
        <figure className="mt-4">
            <svg
                viewBox={`0 0 ${w} ${h}`}
                className="h-32 w-full"
                preserveAspectRatio="none"
                role="img"
                aria-label={t('Score went from {first} to {last} over the last {count} scans', { first, last, count: points.length })}
            >
                <path d={`${path} L${w},${h} L0,${h} Z`} className="fill-emerald-500/10" />
                <path d={path} fill="none" strokeWidth="2.5" vectorEffect="non-scaling-stroke" className="stroke-emerald-600" />
            </svg>
            <figcaption className="text-muted-foreground mt-2 flex justify-between text-xs">
                <span>{formatDay(points[0].date)}</span>
                <span>
                    {first} → <span className="text-foreground font-semibold">{last}</span>
                </span>
                <span>{formatDay(points[points.length - 1].date)}</span>
            </figcaption>
        </figure>
    );
}
