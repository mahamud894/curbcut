import { t, tk } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function scoreTone(score: number | null) {
    if (score === null) return { stroke: 'stroke-muted-foreground/40', text: 'text-muted-foreground', label: tk('Not scanned yet') };
    if (score >= 90) return { stroke: 'stroke-emerald-600', text: 'text-emerald-700 dark:text-emerald-400', label: tk('Good') };
    if (score >= 70) return { stroke: 'stroke-amber-500', text: 'text-amber-700 dark:text-amber-400', label: tk('Needs work') };
    return { stroke: 'stroke-red-600', text: 'text-red-700 dark:text-red-400', label: tk('Poor') };
}

export function ScoreRing({ score, size = 96, className }: { score: number | null; size?: number; className?: string }) {
    const radius = 42;
    const circumference = 2 * Math.PI * radius;
    const tone = scoreTone(score);
    const offset = circumference * (1 - (score ?? 0) / 100);

    return (
        <div className={cn('relative inline-flex items-center justify-center', className)} style={{ width: size, height: size }}>
            <svg viewBox="0 0 100 100" className="-rotate-90" width={size} height={size} aria-hidden="true">
                <circle cx="50" cy="50" r={radius} fill="none" strokeWidth="9" className="stroke-muted" />
                <circle
                    cx="50"
                    cy="50"
                    r={radius}
                    fill="none"
                    strokeWidth="9"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className={cn('transition-[stroke-dashoffset] duration-700', tone.stroke)}
                />
            </svg>
            <span className="absolute flex flex-col items-center leading-none">
                <span className={cn('font-bold tabular-nums', tone.text)} style={{ fontSize: size * 0.28 }}>
                    {score ?? '–'}
                </span>
                <span className="sr-only">
                    {score === null ? t(tone.label) : t('Accessibility score {score} out of 100, {rating}', { score, rating: t(tone.label) })}
                </span>
            </span>
        </div>
    );
}
