import { m } from 'motion/react';
import { EASE_OUT, ONCE } from '@/components/landing/landing-motion';

/**
 * A thin track that fills to `value` (0 to 1) the first time it scrolls into view. The track watches the view: the
 * fill starts at zero width, and something with no size never counts as seen.
 */
export default function StepBar({
    value,
    delay = 0.3,
}: {
    value: number;
    delay?: number;
}) {
    return (
        <m.span
            initial="empty"
            whileInView="filled"
            viewport={ONCE}
            className="block h-2 overflow-hidden rounded-full bg-border"
        >
            <m.span
                variants={{
                    empty: { transform: 'scaleX(0)' },
                    filled: { transform: `scaleX(${value})` },
                }}
                transition={{ duration: 0.9, ease: EASE_OUT, delay }}
                className="block h-full origin-left rounded-full bg-primary"
            />
        </m.span>
    );
}
