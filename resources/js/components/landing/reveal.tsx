import type { ReactNode } from 'react';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

/** Rises 16 px out of a slight blur the first time it scrolls into view (hooks/use-scroll-reveal.ts); with reduced motion it only fades. */
export default function Reveal({
    delay = 0,
    amount,
    className,
    children,
}: {
    delay?: number;
    /** How much of it must show before it plays (a fifth by default): a card that only peeks out of a carousel needs less. */
    amount?: number;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            data-reveal="idle"
            {...(amount === undefined ? {} : { 'data-reveal-amount': amount })}
            className={cn('reveal-rise', enterDelay(delay), className)}
        >
            {children}
        </div>
    );
}
