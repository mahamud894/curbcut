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
import type { SiteSummary } from '@/types/curbcut';
import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Person {
    id: number;
    name: string;
    email: string;
}

export default function SiteSettings({
    site,
    owner,
    members,
    canManageMembers,
}: {
    site: SiteSummary;
    owner: Person;
    members: Person[];
    canManageMembers: boolean;
}) {
    return (
        <SiteLayout site={site} title="Settings">
            <section aria-labelledby="settings-heading" className="bg-card max-w-2xl rounded-xl border p-6 shadow-xs">
                <h2 id="settings-heading" className="mb-5 font-semibold">
                    Scan settings
                </h2>
                <SiteForm site={site} />
            </section>

            <Members siteId={site.id} owner={owner} members={members} canManage={canManageMembers} />

            {canManageMembers && (
                <section aria-labelledby="danger-heading" className="max-w-2xl rounded-xl border border-red-200 p-6 dark:border-red-900">
                    <h2 id="danger-heading" className="font-semibold text-red-800 dark:text-red-300">
                        Delete this site
                    </h2>
                    <p className="text-muted-foreground mt-1 mb-4 text-sm">
                        This removes the site with all of its scans, pages and issue history. It cannot be undone.
                    </p>
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">Delete site</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Delete {site.name}?</DialogTitle>
                                <DialogDescription>All scans and issue history for this site will be permanently deleted.</DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button variant="destructive" onClick={() => router.delete(`/sites/${site.id}`)}>
                                    Delete permanently
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </section>
            )}
        </SiteLayout>
    );
}

function Members({ siteId, owner, members, canManage }: { siteId: number; owner: Person; members: Person[]; canManage: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ email: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/sites/${siteId}/members`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section aria-labelledby="members-heading" className="bg-card max-w-2xl rounded-xl border p-6 shadow-xs">
            <h2 id="members-heading" className="font-semibold">
                People with access
            </h2>
            <p className="text-muted-foreground mt-1 mb-4 text-sm">
                Members can triage issues, fill in the checklist and edit the statement. Only the owner can manage access or delete the site.
            </p>
            <ul className="divide-y rounded-lg border">
                <li className="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <span>
                        <span className="font-medium">{owner.name}</span>
                        <span className="text-muted-foreground block text-xs">{owner.email}</span>
                    </span>
                    <span className="text-muted-foreground text-xs font-medium">Owner</span>
                </li>
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
                                Remove<span className="sr-only"> {m.name}</span>
                            </Button>
                        ) : (
                            <span className="text-muted-foreground text-xs font-medium">Member</span>
                        )}
                    </li>
                ))}
            </ul>
            {canManage && (
                <form onSubmit={submit} className="mt-4 grid gap-2">
                    <Label htmlFor="member-email">Add a person by email</Label>
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
                            Add
                        </Button>
                    </div>
                    <p id="member-help" className="text-muted-foreground text-xs">
                        They need a Curbcut account on this installation first.
                    </p>
                    <InputError message={errors.email} />
                </form>
            )}
        </section>
    );
}
