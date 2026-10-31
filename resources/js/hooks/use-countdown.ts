import { useEffect, useState } from 'react';

/** Seconds left from `seconds` down to zero, one tick a second; `restart` starts it over. */
export function useCountdown(seconds: number) {
    const [left, setLeft] = useState(seconds);

    useEffect(() => {
        if (left <= 0) {
            return;
        }

        const timer = setTimeout(() => setLeft(left - 1), 1000);

        return () => clearTimeout(timer);
    }, [left]);

    return { left, restart: () => setLeft(seconds) };
}
