import { useEffect, useState } from 'react';

const STEP = 55;

function reducedMotion(): boolean {
    return (
        typeof window === 'undefined' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/** How many words of a sentence show while it is being written out: one more every 55 ms once `start`; all at once with reduced motion. */
export function useTypewriter(words: number, start: boolean): number {
    const [shown, setShown] = useState(0);
    const still = reducedMotion();

    useEffect(() => {
        if (still || !start) {
            return;
        }

        const timer = window.setInterval(() => {
            setShown((count) => {
                if (count + 1 >= words) {
                    window.clearInterval(timer);
                }

                return Math.min(words, count + 1);
            });
        }, STEP);

        return () => window.clearInterval(timer);
    }, [words, start, still]);

    return still ? words : shown;
}
