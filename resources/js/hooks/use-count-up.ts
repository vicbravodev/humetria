import { useEffect, useState } from 'react';

const DURATION = 1200;

function reducedMotion(): boolean {
    return (
        typeof window === 'undefined' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * A figure of the report counting up to its value once, as it opens (1.2 s, easing out); at once with reduced motion, and at
 * once when the report goes to print: paper never carries a frame of the count.
 */
export function useCountUp(target: number): number {
    const [value, setValue] = useState(0);
    const [printing, setPrinting] = useState(false);
    const still = reducedMotion() || printing;

    useEffect(() => {
        const stop = (): void => setPrinting(true);
        window.addEventListener('beforeprint', stop);

        return () => window.removeEventListener('beforeprint', stop);
    }, []);

    useEffect(() => {
        if (still) {
            return;
        }

        let frame = 0;
        let start: number | null = null;
        const step = (time: number): void => {
            start ??= time;
            const progress = Math.min(1, (time - start) / DURATION);
            setValue(Math.round(target * (1 - (1 - progress) ** 3)));

            if (progress < 1) {
                frame = requestAnimationFrame(step);
            }
        };
        frame = requestAnimationFrame(step);

        return () => cancelAnimationFrame(frame);
    }, [target, still]);

    return still ? target : value;
}
