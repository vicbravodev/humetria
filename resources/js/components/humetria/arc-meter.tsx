import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

// The shape of the "h" in the logo: up one leg, over the arch, down the other.
const ARC = 'M10 49V33A22 22 0 0 1 54 33V49';

/**
 * How far something has come (answers, participation), drawn as the arch of the logo's "h" (pantallas: Panel,
 * Eval-Activa). The figure and what it counts go inside; the arch alone is never the only way to read it.
 */
export default function ArcMeter({
    value,
    max,
    label,
    size = 'lg',
    children,
}: {
    value: number;
    max: number;
    /** Read by screen readers instead of the drawing, e.g. "18 de 60 contestaron". */
    label: string;
    size?: 'sm' | 'md' | 'lg';
    children?: ReactNode;
}) {
    const percent = max <= 0 ? 0 : Math.min(100, (value / max) * 100);

    return (
        <div
            role="img"
            aria-label={label}
            data-slot="arc-meter"
            className={cn(
                'relative shrink-0',
                size === 'sm' && 'h-[50px] w-[68px]',
                size === 'md' && 'h-[113px] w-[156px]',
                size === 'lg' && 'h-[141px] w-[196px]',
            )}
        >
            <svg
                viewBox="0 5 64 46"
                aria-hidden
                className="block size-full overflow-visible"
            >
                <path
                    d={ARC}
                    fill="none"
                    className="stroke-track"
                    strokeWidth={5.22}
                    strokeLinecap="round"
                    pathLength={100}
                />
                <path
                    d={ARC}
                    fill="none"
                    className="stroke-primary transition-[stroke-dashoffset] duration-700 ease-out motion-reduce:transition-none"
                    strokeWidth={5.22}
                    strokeLinecap="round"
                    pathLength={100}
                    strokeDasharray={100}
                    strokeDashoffset={100 - percent}
                    // An empty meter draws nothing, not a dot from the round cap.
                    visibility={percent === 0 ? 'hidden' : 'visible'}
                />
            </svg>
            {children !== undefined && (
                <div className="absolute inset-x-0 bottom-0.5 flex flex-col items-center gap-0.5 text-center">
                    {children}
                </div>
            )}
        </div>
    );
}
