import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

export function Stat({ label, value, hint, tone, icon }: { label: string; value: ReactNode; hint?: ReactNode; tone?: string; icon?: ReactNode }) {
    return (
        <div className="bg-card rounded-xl border p-4 shadow-xs">
            <div className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                {icon}
                {label}
            </div>
            <div className={cn('mt-2 text-3xl font-bold tracking-tight tabular-nums', tone)}>{value}</div>
            {hint && <div className="text-muted-foreground mt-1 text-xs">{hint}</div>}
        </div>
    );
}
