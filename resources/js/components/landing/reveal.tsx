import type { ReactNode } from 'react';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

/** Rises 16 px out of a slight blur the first time it scrolls into view (hooks/use-scroll-reveal.ts); with reduced motion it only fades. */
export default function Reveal({
    delay = 0,
    className,
    children,
}: {
    delay?: number;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            data-reveal="idle"
            className={cn('reveal-rise', enterDelay(delay), className)}
        >
            {children}
        </div>
    );
}
