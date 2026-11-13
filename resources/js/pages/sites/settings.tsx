import { SiteForm } from '@/components/curbcut/site-form';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SiteLayout from '@/layouts/site-layout';
import { t, tRich } from '@/lib/i18n';
import type { SiteSummary } from '@/types/curbcut';
import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

interface Person {
    id: number;
    name: string;
    email: string;
}

interface WorkspaceInfo {
    id: number;
    name: string;
    people: number;
}

export default function SiteSettings({
    site,
    siteWorkspace: workspace,
    members,
    canManageMembers,
}: {
    site: SiteSummary;
    siteWorkspace: WorkspaceInfo;
    members: Person[];
    canManageMembers: boolean;
}) {
    return (
        <SiteLayout site={site} title={t('Settings')}>
            <section aria-labelledby="settings-heading" className="bg-card max-w-2xl rounded-xl border p-6 shadow-xs">
                <h2 id="settings-heading" className="mb-5 font-semibold">
                    {t('Scan settings')}
                </h2>
                <SiteForm site={site} />
            </section>

            <Members siteId={site.id} workspace={workspace} members={members} canManage={canManageMembers} />

            {canManageMembers && (
                <section aria-labelledby="danger-heading" className="max-w-2xl rounded-xl border border-red-200 p-6 dark:border-red-900">
                    <h2 id="danger-heading" className="font-semibold text-red-800 dark:text-red-300">
                        {t('Delete this site')}
                    </h2>
                    <p className="text-muted-foreground mt-1 mb-4 text-sm">
                        {t('This removes the site with all of its scans, pages and issue history. It cannot be undone.')}
                    </p>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">{t('Delete site')}</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Delete {site}?', { site: site.name })}</DialogTitle>
                                <DialogDescription>{t('All scans and issue history for this site will be permanently deleted.')}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary">{t('Cancel')}</Button>
                                </DialogClose>
                                <Button variant="destructive" onClick={() => router.delete(`/sites/${site.id}`)}>
                                    {t('Delete permanently')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </section>
            )}
        </SiteLayout>
    );
}

function Members({ siteId, workspace, members, canManage }: { siteId: number; workspace: WorkspaceInfo; members: Person[]; canManage: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/sites/${siteId}/members`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section aria-labelledby="members-heading" className="bg-card max-w-2xl rounded-xl border p-6 shadow-xs">
            <h2 id="members-heading" className="font-semibold">
                {t('Who has access')}
            </h2>
            <p className="text-muted-foreground mt-1 text-sm">
                {tRich(
                    'Everyone in the <link>{workspace}</link> workspace ({count, plural, one {# person} other {# people}}) can work on this site.',
                    {
                        workspace: workspace.name,
                        count: workspace.people,
                        link: (c: ReactNode) => (
                            <a href={`/workspaces/${workspace.id}/settings`} className="text-foreground font-medium underline underline-offset-4">
                                {c}
                            </a>
                        ),
                    },
                )}
            </p>
            <h3 className="mt-5 mb-1 text-sm font-semibold">{t('Guests')}</h3>
            <p className="text-muted-foreground mb-3 text-sm">
                {t(
                    "Guests, such as a client's developer, get access to this site only. They can triage issues, fill in the checklist and edit the statement.",
                )}
            </p>
            {members.length === 0 ? (
                <p className="text-muted-foreground rounded-lg border border-dashed px-4 py-3 text-sm">{t('No guests yet.')}</p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {members.map((m) => (
                        <li key={m.id} className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                            <span>
                                <span className="font-medium">{m.name}</span>
                                <span className="text-muted-foreground block text-xs">{m.email}</span>
                            </span>
                            {canManage ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => router.delete(`/sites/${siteId}/members/${m.id}`, { preserveScroll: true })}
                                >
                                    {t('Remove')}
                                    <span className="sr-only"> {m.name}</span>
                                </Button>
                            ) : (
                                <span className="text-muted-foreground text-xs font-medium">{t('Guest')}</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {canManage && (
                <form onSubmit={submit} className="mt-4 grid gap-2">
                    <Label htmlFor="member-email">{t('Add a guest by email')}</Label>
                    <div className="flex gap-2">
                        <Input
                            id="member-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="colleague@example.com"
                            aria-describedby="member-help"
                        />
                        <Button type="submit" disabled={processing || !data.email}>
                            {t('Add')}
                        </Button>
                    </div>
                    <p id="member-help" className="text-muted-foreground text-xs">
                        {t('They need a Curbcut account on this installation first.')}
                    </p>
                    <InputError message={errors.email} />
                </form>
            )}
        </section>
    );
}
