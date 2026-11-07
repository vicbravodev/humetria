import { m } from 'motion/react';
import type { ReactNode } from 'react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

/** Rises 16 px out of a slight blur the first time it scrolls into view; with reduced motion it only fades. */
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
        <m.div
            initial={{
                opacity: 0,
                transform: 'translateY(16px)',
                filter: 'blur(4px)',
            }}
            whileInView={{
                opacity: 1,
                transform: 'translateY(0px)',
                filter: 'blur(0px)',
            }}
            viewport={ONCE}
            transition={{ ...ENTER, delay }}
            className={className}
        >
            {children}
        </m.div>
    );
}
