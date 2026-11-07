import { m } from 'motion/react';
import { EASE_OUT, ONCE } from '@/components/landing/landing-motion';

/** A bar that grows from the left to its share the first time it scrolls into view. */
export default function GrowBar({
    share,
    delay = 0,
}: {
    /** From 0 to 1. */
    share: number;
    delay?: number;
}) {
    return (
        <span
            aria-hidden
            className="block h-2 overflow-hidden rounded-full bg-track"
        >
            <m.span
                initial={{ transform: 'scaleX(0)' }}
                whileInView={{ transform: `scaleX(${share})` }}
                viewport={ONCE}
                transition={{ duration: 1, ease: EASE_OUT, delay }}
                className="block h-full origin-left rounded-full bg-primary"
            />
        </span>
    );
}
