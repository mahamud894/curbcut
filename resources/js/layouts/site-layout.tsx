import { Flash } from '@/components/curbcut/flash';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SiteSummary } from '@/types/curbcut';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, LoaderCircle, Play } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

const tabs = [
    { label: 'Overview', suffix: '' },
    { label: 'Issues', suffix: '/issues' },
    { label: 'Pages', suffix: '/pages' },
    { label: 'Checklist', suffix: '/checklist' },
    { label: 'Statement', suffix: '/statement' },
    { label: 'Settings', suffix: '/settings' },
];

export default function SiteLayout({ site, title, children }: { site: SiteSummary; title: string; children: ReactNode }) {
    const { url } = usePage();
    const scan = site.latest_scan;
    const active = scan && (scan.status === 'queued' || scan.status === 'running');

    // While a scan is running, refresh the page data every few seconds to show progress.
    useEffect(() => {
        if (!active) return;
        const timer = window.setInterval(() => router.reload({ preserveUrl: true }), 3000);
        return () => window.clearInterval(timer);
    }, [active]);

    const base = `/sites/${site.id}`;
    const currentPath = url.split('?')[0];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Sites', href: '/sites' },
                { title: site.name, href: base },
            ]}
        >
            <Head title={`${title} · ${site.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-bold tracking-tight">{site.name}</h1>
                        <a
                            href={site.url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-muted-foreground hover:text-foreground mt-1 inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline"
                        >
                            {site.url}
                            <ExternalLink className="size-3.5" aria-hidden="true" />
                            <span className="sr-only">(opens in a new tab)</span>
                        </a>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Last scanned {relativeTime(site.last_scanned_at)} ·{' '}
                            {site.scan_frequency === 'manual' ? 'Manual scans' : `Scans ${site.scan_frequency}`}
                        </p>
                    </div>
                    <Button
                        onClick={() => router.post(`${base}/scans`, {}, { preserveScroll: true })}
                        disabled={Boolean(active)}
                        className="shrink-0"
                    >
                        {active ? <LoaderCircle className="animate-spin" aria-hidden="true" /> : <Play aria-hidden="true" />}
                        {active ? 'Scanning…' : 'Scan now'}
                    </Button>
                </div>

                {active && scan && (
                    <div
                        role="status"
                        aria-live="polite"
                        className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100"
                    >
                        <div className="flex items-center justify-between gap-4 font-medium">
                            <span>{scan.status === 'queued' ? 'Scan queued, waiting for a worker…' : 'Scanning pages…'}</span>
                            {scan.pages_total > 0 && (
                                <span className="tabular-nums">
                                    {scan.pages_scanned + scan.pages_failed} / {scan.pages_total} pages
                                </span>
                            )}
                        </div>
                        {scan.pages_total > 0 && (
                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-blue-200 dark:bg-blue-900" aria-hidden="true">
                                <div
                                    className="h-full rounded-full bg-blue-600 transition-all"
                                    style={{ width: `${Math.round(((scan.pages_scanned + scan.pages_failed) / scan.pages_total) * 100)}%` }}
                                />
                            </div>
                        )}
                    </div>
                )}

                <Flash />

                <nav aria-label="Site sections" className="border-b">
                    <ul className="-mb-px flex gap-1 overflow-x-auto">
                        {tabs.map((tab) => {
                            const href = `${base}${tab.suffix}`;
                            const isCurrent = tab.suffix === '/issues' ? currentPath.startsWith(href) : currentPath === href;
                            return (
                                <li key={tab.label}>
                                    <Link
                                        href={href}
                                        aria-current={isCurrent ? 'page' : undefined}
                                        className={cn(
                                            'inline-flex border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                                            isCurrent
                                                ? 'border-foreground text-foreground'
                                                : 'text-muted-foreground hover:text-foreground border-transparent',
                                        )}
                                    >
                                        {tab.label}
                                        {tab.label === 'Issues' && site.open_issues_count > 0 && (
                                            <span className="bg-muted text-foreground ml-2 rounded-full px-2 text-xs leading-5 tabular-nums">
                                                {site.open_issues_count}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                {children}
            </div>
        </AppLayout>
    );
}
