import { useEffect, useRef, useState } from 'react';

/**
 * A form whose request did not reach the server for lack of a network (V3-Estados, «Portal sin conexión»): it waits, with
 * what the person answered still in the page, and sends it again by itself when the browser is back online. `failed`
 * goes to the visit's `onNetworkError`, `sent` to its `onSuccess`.
 */
export function useResendWhenOnline(resend: () => void) {
    const [waiting, setWaiting] = useState(false);
    const latest = useRef(resend);

    useEffect(() => {
        latest.current = resend;
    });

    useEffect(() => {
        if (!waiting) {
            return;
        }

        const online = () => latest.current();
        window.addEventListener('online', online);

        return () => window.removeEventListener('online', online);
    }, [waiting]);

    return {
        waiting,
        failed: () => {
            setWaiting(true);

            // Handled here: Inertia does not report it as an exception.
            return false;
        },
        sent: () => setWaiting(false),
    };
}
