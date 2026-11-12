import { SiteForm } from '@/components/curbcut/site-form';
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
import SiteLayout from '@/layouts/site-layout';
import type { SiteSummary } from '@/types/curbcut';
import { router } from '@inertiajs/react';

export default function SiteSettings({ site }: { site: SiteSummary }) {
    return (
        <SiteLayout site={site} title="Settings">
            <section aria-labelledby="settings-heading" className="bg-card max-w-2xl rounded-xl border p-6 shadow-xs">
                <h2 id="settings-heading" className="mb-5 font-semibold">
                    Scan settings
                </h2>
                <SiteForm site={site} />
            </section>

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
        </SiteLayout>
    );
}
