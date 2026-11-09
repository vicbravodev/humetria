import { useEffect, useState } from 'react';

/** The time of this device's clock, renewed every `everyMs`: for a countdown or a clock that moves on its own. */
export function useNow(everyMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), everyMs);

        return () => window.clearInterval(timer);
    }, [everyMs]);

    return now;
}
