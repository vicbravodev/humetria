import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { useCountdown } from '@/hooks/use-countdown';
import { timeLeftText } from '@/lib/support-entry';

/** The time left of an entry as support; at zero it asks the server again, which ends the entry and goes back to the console. */
export function ImpersonationCountdown({ seconds }: { seconds: number }) {
    const { left } = useCountdown(seconds);

    useEffect(() => {
        if (left === 0) {
            router.reload();
        }
    }, [left]);

    return (
        <span className="tabular-nums" role="timer" aria-live="off">
            {timeLeftText(left)}
        </span>
    );
}
