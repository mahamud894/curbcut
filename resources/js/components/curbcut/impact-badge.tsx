import { cn } from '@/lib/utils';
import type { Impact } from '@/types/curbcut';

// Text colours are dark enough for WCAG AA contrast on their light backgrounds.
const styles: Record<Impact, string> = {
    critical: 'bg-red-100 text-red-800 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900',
    serious: 'bg-orange-100 text-orange-800 ring-orange-200 dark:bg-orange-950 dark:text-orange-200 dark:ring-orange-900',
    moderate: 'bg-amber-100 text-amber-900 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900',
    minor: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
};

export const impactOrder: Impact[] = ['critical', 'serious', 'moderate', 'minor'];

export const impactDot: Record<Impact, string> = {
    critical: 'bg-red-600',
    serious: 'bg-orange-500',
    moderate: 'bg-amber-400',
    minor: 'bg-slate-400',
};

export function ImpactBadge({ impact, className }: { impact: Impact; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset',
                styles[impact],
                className,
            )}
        >
            {impact}
        </span>
    );
}
