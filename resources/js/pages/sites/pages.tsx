import SiteLayout from '@/layouts/site-layout';
import { shortUrl } from '@/lib/format';
import { t } from '@/lib/i18n';
import type { SiteSummary } from '@/types/curbcut';
import { Link } from '@inertiajs/react';

interface PageRow {
    id: number;
    url: string;
    title: string | null;
    open_issues: number;
    critical: number;
    last_result: { status: 'ok' | 'failed'; http_status: number | null; error: string | null; duration_ms: number | null } | null;
}

export default function SitePages({ site, pages }: { site: SiteSummary; pages: PageRow[] }) {
    return (
        <SiteLayout site={site} title={t('Pages')}>
            {pages.length === 0 ? (
                <p className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
                    {t('Pages appear here after the first scan.')}
                </p>
            ) : (
                <div className="bg-card overflow-x-auto rounded-xl border shadow-xs">
                    <table className="w-full text-sm">
                        <caption className="sr-only">{t('Scanned pages and their open issues')}</caption>
                        <thead className="text-muted-foreground border-b text-left text-xs uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5 font-medium">
                                    {t('Page')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 font-medium">
                                    {t('Last scan')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Critical')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Open issues')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {pages.map((page) => (
                                <tr key={page.id} className="hover:bg-muted/50">
                                    <td className="max-w-md px-4 py-3">
                                        <Link
                                            href={`/sites/${site.id}/issues?page=${page.id}`}
                                            className="block truncate font-medium underline-offset-4 hover:underline"
                                        >
                                            {page.title || shortUrl(page.url)}
                                        </Link>
                                        <span className="text-muted-foreground block truncate text-xs">{page.url}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {!page.last_result ? (
                                            <span className="text-muted-foreground">{t('Not in last scan')}</span>
                                        ) : page.last_result.status === 'ok' ? (
                                            <span className="text-emerald-700 dark:text-emerald-400">{t('Scanned')}</span>
                                        ) : (
                                            <span className="text-red-700 dark:text-red-400" title={page.last_result.error ?? undefined}>
                                                {t('Failed')}
                                                {page.last_result.http_status ? ` (HTTP ${page.last_result.http_status})` : ''}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {page.critical > 0 ? (
                                            <span className="font-semibold text-red-700 dark:text-red-400">{page.critical}</span>
                                        ) : (
                                            0
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold tabular-nums">{page.open_issues}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </SiteLayout>
    );
}
