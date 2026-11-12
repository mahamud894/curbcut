import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert } from 'lucide-react';

export function Flash() {
    const { flash } = usePage<SharedData & { flash?: { success?: string | null; error?: string | null } }>().props;
    if (!flash?.success && !flash?.error) return null;
    const isError = Boolean(flash.error);

    return (
        <div
            role={isError ? 'alert' : 'status'}
            className={
                isError
                    ? 'flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
                    : 'flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
            }
        >
            {isError ? (
                <TriangleAlert className="size-4 shrink-0" aria-hidden="true" />
            ) : (
                <CheckCircle2 className="size-4 shrink-0" aria-hidden="true" />
            )}
            {flash.error ?? flash.success}
        </div>
    );
}
