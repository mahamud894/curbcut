import { Flash } from '@/components/curbcut/flash';
import { ScoreRing } from '@/components/curbcut/score-ring';
import { SiteForm } from '@/components/curbcut/site-form';
import { Stat } from '@/components/curbcut/stat';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { plural, relativeTime } from '@/lib/format';
import type { SiteSummary } from '@/types/curbcut';
import { Head, Link } from '@inertiajs/react';
import { AlertOctagon, Globe, Plus, ShieldCheck, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

interface Props {
    sites: SiteSummary[];
    totals: { sites: number; open_issues: number; critical: number; average_score: number | null };
}

export default function SitesIndex({ sites, totals }: Props) {
    const [open, setOpen] = useState(false);

    const addDialog = (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden="true" />
                    Add site
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add a website</DialogTitle>
                    <DialogDescription>Curbcut will find its pages and check them against WCAG 2.2 AA.</DialogDescription>
                </DialogHeader>
                <SiteForm onSuccess={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );

    return (
        <AppLayout breadcrumbs={[{ title: 'Sites', href: '/sites' }]}>
            <Head title="Sites" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Sites</h1>
                        <p className="text-muted-foreground text-sm">Accessibility health of every website you monitor.</p>
                    </div>
                    {addDialog}
                </div>

                <Flash />

                {sites.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-16 text-center">
                        <div className="bg-muted mb-4 rounded-full p-3">
                            <ShieldCheck className="size-6" aria-hidden="true" />
                        </div>
                        <h2 className="text-lg font-semibold">Add your first website</h2>
                        <p className="text-muted-foreground mt-1 mb-6 max-w-md text-sm">
                            Curbcut scans every page with axe-core, tracks what is new and what got fixed, and keeps a history you can show to
                            auditors.
                        </p>
                        <Button onClick={() => setOpen(true)}>
                            <Plus aria-hidden="true" />
                            Add your first site
                        </Button>
                    </div>
                ) : (
                    <>
                        <section aria-label="Summary" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Stat label="Sites" value={totals.sites} icon={<Globe className="size-4" aria-hidden="true" />} />
                            <Stat
                                label="Average score"
                                value={totals.average_score ?? '–'}
                                hint="Out of 100, latest scans"
                                icon={<ShieldCheck className="size-4" aria-hidden="true" />}
                            />
                            <Stat
                                label="Open issues"
                                value={totals.open_issues.toLocaleString()}
                                icon={<TriangleAlert className="size-4" aria-hidden="true" />}
                            />
                            <Stat
                                label="Critical issues"
                                value={totals.critical.toLocaleString()}
                                tone={totals.critical > 0 ? 'text-red-700 dark:text-red-400' : undefined}
                                icon={<AlertOctagon className="size-4" aria-hidden="true" />}
                            />
                        </section>

                        <section aria-labelledby="sites-heading">
                            <h2 id="sites-heading" className="sr-only">
                                Your sites
                            </h2>
                            <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {sites.map((site) => {
                                    const done = site.latest_completed_scan;
                                    const running = site.latest_scan && ['queued', 'running'].includes(site.latest_scan.status);
                                    return (
                                        <li key={site.id}>
                                            <Link
                                                href={`/sites/${site.id}`}
                                                className="bg-card hover:border-foreground/30 focus-visible:ring-ring flex h-full items-center gap-4 rounded-xl border p-5 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                            >
                                                <ScoreRing score={done?.score ?? null} size={72} />
                                                <div className="min-w-0 flex-1">
                                                    <h3 className="truncate font-semibold">{site.name}</h3>
                                                    <p className="text-muted-foreground truncate text-sm">{site.url}</p>
                                                    <p className="mt-2 text-sm">
                                                        {done ? (
                                                            <>
                                                                <span className="font-medium">{plural(site.open_issues_count, 'open issue')}</span>
                                                                {done.critical > 0 && (
                                                                    <span className="text-red-700 dark:text-red-400">
                                                                        {' '}
                                                                        · {done.critical} critical
                                                                    </span>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <span className="text-muted-foreground">No completed scan yet</span>
                                                        )}
                                                    </p>
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        {running ? 'Scan in progress…' : `Scanned ${relativeTime(site.last_scanned_at)}`}
                                                    </p>
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
