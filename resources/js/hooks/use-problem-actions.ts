import { useState } from 'react';
import { clockNow } from '@/lib/attendance/correction';
import type { ProblemAction } from '@/lib/attendance/punch-problem';
import { hrLinkMessage, LOCATION_HOWTO } from '@/lib/attendance/punch-problem';

/**
 * What the buttons of a problem do: mark again, close it, open the form of a correction (at the time on the company's
 * clock now), say how to allow the location, or copy the message that asks RH for the link of the phone.
 */
export function useProblemActions({
    company,
    timezone,
    onRetry,
    onDismiss,
}: {
    company: string;
    timezone: string;
    onRetry: () => void;
    onDismiss: () => void;
}) {
    const [asking, setAsking] = useState<{ date: string; time: string } | null>(
        null,
    );
    const [howto, setHowto] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const run = (kind: ProblemAction['kind']) => {
        switch (kind) {
            case 'retry':
                onRetry();
                break;
            case 'dismiss':
                onDismiss();
                break;
            case 'correction':
                setAsking(clockNow(new Date(), timezone));
                break;
            case 'howto':
                setHowto(LOCATION_HOWTO);
                break;
            case 'copy-hr':
                void navigator.clipboard
                    .writeText(hrLinkMessage(company))
                    .then(() => setCopied(true));
                break;
        }
    };

    return { asking, howto, copied, run, close: () => setAsking(null) };
}
