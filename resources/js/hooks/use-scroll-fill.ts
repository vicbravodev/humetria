import { useMotionValueEvent, useScroll } from 'motion/react';
import type { RefObject } from 'react';
import { useRef } from 'react';

/**
 * Fills a rail as `target` scrolls past: returns the ref of the element to fill, which gets `--p` (0 to 1) through the
 * CSSOM, the one way to move something with the scroll that the CSP allows (a `style` attribute printed by the server is not).
 */
export function useScrollFill(
    target: RefObject<HTMLElement | null>,
): RefObject<HTMLSpanElement | null> {
    const fill = useRef<HTMLSpanElement>(null);
    const { scrollYProgress } = useScroll({
        target,
        offset: ['start 70%', 'end 75%'],
    });

    useMotionValueEvent(scrollYProgress, 'change', (progress) => {
        fill.current?.style.setProperty('--p', String(progress));
    });

    return fill;
}
