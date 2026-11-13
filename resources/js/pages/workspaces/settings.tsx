import { Flash } from '@/components/curbcut/flash';
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
import AppLayout from '@/layouts/app-layout';
import { capitalize } from '@/lib/format';
import { t, tk } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Role = 'owner' | 'admin' | 'member';
interface Person {
    id: number;
    name: string;
    email: string;
    role: Role;
}

interface Props {
    organization: { id: number; name: string; personal: boolean };
    people: Person[];
    myRole: Role;
    canDelete: boolean;
}

const roleHelp: Record<Role, string> = {
    owner: tk('Everything, including deleting the workspace'),
    admin: tk('Manage people, settings and sites'),
    member: tk('Add sites, scan, triage issues, checklist and statements'),
};

const selectClass = 'border-input bg-background h-9 rounded-md border px-3 text-sm';

export default function WorkspaceSettings({ organization: workspace, people, myRole, canDelete }: Props) {
    const manager = myRole === 'owner' || myRole === 'admin';
    const base = `/workspaces/${workspace.id}`;
    const roles: Role[] = myRole === 'owner' ? ['owner', 'admin', 'member'] : ['admin', 'member'];

    const rename = useForm({ name: workspace.name });
    const invite = useForm({ email: '', role: 'member' as Role });
    const remove = useForm({ confirm: '' });

    return (
        <AppLayout
            breadcrumbs={[
                { title: tk('All workspaces'), href: '/workspaces' },
                { title: workspace.name, href: `${base}/settings` },
            ]}
        >
            <Head title={`${t('Workspace settings')} · ${workspace.name}`} />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <h1 className="text-2xl font-bold tracking-tight">{t('Workspace settings')}</h1>
                <Flash />

                <section aria-labelledby="name-heading" className="bg-card rounded-xl border p-6 shadow-xs">
                    <h2 id="name-heading" className="mb-4 font-semibold">
                        {t('Name')}
                    </h2>
                    <form
                        className="flex flex-wrap items-end gap-2"
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            rename.put(base, { preserveScroll: true });
                        }}
                    >
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="ws-name">{t('Workspace name')}</Label>
                            <Input
                                id="ws-name"
                                value={rename.data.name}
                                onChange={(e) => rename.setData('name', e.target.value)}
                                disabled={!manager}
                            />
                            <InputError message={rename.errors.name} />
                        </div>
                        {manager && (
                            <Button type="submit" disabled={rename.processing || rename.data.name === workspace.name}>
                                {t('Save')}
                            </Button>
                        )}
                    </form>
                </section>

                <section aria-labelledby="people-heading" className="bg-card rounded-xl border p-6 shadow-xs">
                    <h2 id="people-heading" className="font-semibold">
                        {t('People')}
                    </h2>
                    <p className="text-muted-foreground mt-1 mb-4 text-sm">
                        {t(
                            "Everyone here can see and work on every site in this workspace. To give a client access to just one site, add them as a guest in that site's settings.",
                        )}
                    </p>
                    <ul className="divide-y rounded-lg border">
                        {people.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                                <span className="min-w-0">
                                    <span className="font-medium">{p.name}</span>
                                    <span className="text-muted-foreground block text-xs">{p.email}</span>
                                </span>
                                <span className="flex items-center gap-2">
                                    {manager && (myRole === 'owner' || p.role !== 'owner') ? (
                                        <>
                                            <label htmlFor={`role-${p.id}`} className="sr-only">
                                                {t('Role of {person}', { person: p.name })}
                                            </label>
                                            <select
                                                id={`role-${p.id}`}
                                                className={selectClass}
                                                value={p.role}
                                                onChange={(e) =>
                                                    router.put(`${base}/people/${p.id}`, { role: e.target.value }, { preserveScroll: true })
                                                }
                                            >
                                                {[...new Set([...roles, p.role])].map((r) => (
                                                    <option key={r} value={r}>
                                                        {t(capitalize(r))}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.delete(`${base}/people/${p.id}`, { preserveScroll: true })}
                                            >
                                                {t('Remove')}
                                                <span className="sr-only"> {p.name}</span>
                                            </Button>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground text-xs font-medium">{t(capitalize(p.role))}</span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>

                    {manager && (
                        <form
                            className="mt-5 grid gap-3"
                            onSubmit={(e: FormEvent) => {
                                e.preventDefault();
                                invite.post(`${base}/people`, { preserveScroll: true, onSuccess: () => invite.reset('email') });
                            }}
                        >
                            <h3 className="text-sm font-semibold">{t('Add a person')}</h3>
                            <div className="flex flex-wrap items-end gap-2">
                                <div className="grid min-w-56 flex-1 gap-2">
                                    <Label htmlFor="invite-email">{t('Email')}</Label>
                                    <Input
                                        id="invite-email"
                                        type="email"
                                        value={invite.data.email}
                                        onChange={(e) => invite.setData('email', e.target.value)}
                                        placeholder="colleague@example.com"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-role">{t('Role')}</Label>
                                    <select
                                        id="invite-role"
                                        className={selectClass}
                                        value={invite.data.role}
                                        onChange={(e) => invite.setData('role', e.target.value as Role)}
                                    >
                                        {roles.map((r) => (
                                            <option key={r} value={r}>
                                                {t(capitalize(r))}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <Button type="submit" disabled={invite.processing || !invite.data.email}>
                                    {t('Add')}
                                </Button>
                            </div>
                            <InputError message={invite.errors.email || invite.errors.role} />
                            <ul className="text-muted-foreground grid gap-1 text-xs">
                                {roles.map((r) => (
                                    <li key={r}>
                                        <span className="font-medium">{t(capitalize(r))}:</span> {t(roleHelp[r])}
                                    </li>
                                ))}
                            </ul>
                        </form>
                    )}
                </section>

                {canDelete && (
                    <section aria-labelledby="delete-heading" className="rounded-xl border border-red-200 p-6 dark:border-red-900">
                        <h2 id="delete-heading" className="font-semibold text-red-800 dark:text-red-300">
                            {t('Delete workspace')}
                        </h2>
                        <p className="text-muted-foreground mt-1 mb-4 text-sm">
                            {t('Deletes every site in this workspace with all scans, issues, checklists and statements.')}
                        </p>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">{t('Delete workspace')}</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>{t('Delete {site}?', { site: workspace.name })}</DialogTitle>
                                    <DialogDescription>{t('This cannot be undone. Type the workspace name to confirm.')}</DialogDescription>
                                </DialogHeader>
                                <form
                                    className="grid gap-3"
                                    onSubmit={(e: FormEvent) => {
                                        e.preventDefault();
                                        remove.delete(base);
                                    }}
                                >
                                    <Label htmlFor="confirm-name">{t('Workspace name')}</Label>
                                    <Input
                                        id="confirm-name"
                                        value={remove.data.confirm}
                                        onChange={(e) => remove.setData('confirm', e.target.value)}
                                    />
                                    <InputError message={remove.errors.confirm} />
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                {t('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button type="submit" variant="destructive" disabled={remove.data.confirm !== workspace.name}>
                                            {t('Delete permanently')}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
