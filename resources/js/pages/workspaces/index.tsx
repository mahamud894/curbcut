import { Flash } from '@/components/curbcut/flash';
import { scoreTone } from '@/components/curbcut/score-ring';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { capitalize, formatNumber } from '@/lib/format';
import { t, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface WorkspaceRow {
    id: number;
    name: string;
    role: string;
    sites: number;
    people: number;
    average_score: number | null;
    lowest_score: number | null;
    open_issues: number;
    critical: number;
    overdue: number;
}

export default function Workspaces({ workspaces }: { workspaces: WorkspaceRow[] }) {
    const [open, setOpen] = useState(() => typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('create'));
    const { data, setData, post, processing, errors } = useForm({ name: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/workspaces', { onSuccess: () => setOpen(false) });
    };

    return (
        <AppLayout breadcrumbs={[{ title: tk('All workspaces'), href: '/workspaces' }]}>
            <Head title={t('All workspaces')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{t('All workspaces')}</h1>
                        <p className="text-muted-foreground text-sm">
                            {t('Use one workspace per client or team. This overview shows how each one is doing.')}
                        </p>
                    </div>
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus aria-hidden="true" /> {t('New workspace')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Create a workspace')}</DialogTitle>
                                <DialogDescription>{t('For a client or a team. You can invite people to it afterwards.')}</DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="workspace-name">{t('Name')}</Label>
                                    <Input
                                        id="workspace-name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={t('Acme Corporation')}
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button type="submit" disabled={processing || !data.name.trim()} className="w-fit">
                                    {t('Create workspace')}
                                </Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <Flash />

                <div className="bg-card overflow-x-auto rounded-xl border shadow-xs">
                    <table className="w-full text-sm">
                        <caption className="sr-only">{t('Workspaces with their sites and accessibility health')}</caption>
                        <thead className="text-muted-foreground border-b text-left text-xs uppercase">
                            <tr>
                                <th scope="col" className="px-4 py-2.5 font-medium">
                                    {t('Workspace')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Sites')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Average score')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Lowest score')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Open issues')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Critical')}
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    {t('Overdue')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {workspaces.map((w) => (
                                <tr key={w.id} className="hover:bg-muted/50">
                                    <th scope="row" className="px-4 py-3 text-left font-normal">
                                        <button
                                            type="button"
                                            onClick={() => router.post(`/workspaces/${w.id}/switch`)}
                                            className="font-semibold underline-offset-4 hover:underline"
                                        >
                                            {w.name}
                                        </button>
                                        <span className="text-muted-foreground block text-xs">
                                            {t(capitalize(w.role))} · {t('{count, plural, one {# person} other {# people}}', { count: w.people })}
                                        </span>
                                    </th>
                                    <td className="px-4 py-3 text-right tabular-nums">{w.sites}</td>
                                    <td className={cn('px-4 py-3 text-right font-semibold tabular-nums', scoreTone(w.average_score).text)}>
                                        {w.average_score ?? '–'}
                                    </td>
                                    <td className={cn('px-4 py-3 text-right tabular-nums', scoreTone(w.lowest_score).text)}>
                                        {w.lowest_score ?? '–'}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatNumber(w.open_issues)}</td>
                                    <td
                                        className={cn(
                                            'px-4 py-3 text-right tabular-nums',
                                            w.critical > 0 && 'font-semibold text-red-700 dark:text-red-400',
                                        )}
                                    >
                                        {w.critical}
                                    </td>
                                    <td
                                        className={cn(
                                            'px-4 py-3 text-right tabular-nums',
                                            w.overdue > 0 && 'font-semibold text-red-700 dark:text-red-400',
                                        )}
                                    >
                                        {w.overdue}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
