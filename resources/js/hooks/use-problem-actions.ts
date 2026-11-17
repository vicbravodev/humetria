import { router } from '@inertiajs/react';
import { useState } from 'react';
import { clockNow } from '@/lib/attendance/correction';
import type { ProblemAction } from '@/lib/attendance/punch-problem';
import { hrLinkMessage, LOCATION_HOWTO } from '@/lib/attendance/punch-problem';
import { useCopyMessage } from '@/hooks/use-copy-message';

/**
 * What the buttons of a problem do: mark again, open Marcar again without the old code in its address (to scan the one on
 * screen now), close it, open the form of a correction (at the time of the center's clock now, by the server's time),
 * say how to allow the location, or copy the message that asks RH for the link of the phone.
 */
export function useProblemActions({
    company,
    timezone,
    now,
    onRetry,
    onDismiss,
}: {
    company: string;
    timezone: string;
    /** The server's time now. */
    now: number;
    onRetry: () => void;
    onDismiss: () => void;
}) {
    const [asking, setAsking] = useState<{ date: string; time: string } | null>(
        null,
    );
    const [howto, setHowto] = useState<string | null>(null);
    const { copied, copy } = useCopyMessage();

    const run = (kind: ProblemAction['kind']) => {
        switch (kind) {
            case 'retry':
                onRetry();
                break;
            case 'rescan':
                router.get(window.location.pathname);
                break;
            case 'dismiss':
                onDismiss();
                break;
            case 'correction':
                setAsking(clockNow(new Date(now), timezone));
                break;
            case 'howto':
                setHowto(LOCATION_HOWTO);
                break;
            case 'copy-hr':
                copy(hrLinkMessage(company));
                break;
            case 'reload':
                window.location.reload();
                break;
        }
    };

    return { asking, howto, copied, run, close: () => setAsking(null) };
}
