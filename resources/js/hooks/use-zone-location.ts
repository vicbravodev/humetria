import { useState } from 'react';
import { currentPosition } from '@/lib/attendance/geolocation';
import type { Reading } from '@/lib/attendance/geolocation';
import { accuracyText, locationErrorText } from '@/lib/attendance/zone';

type Status = { busy: boolean; text: string | null; failed: boolean };

/**
 * «Usar mi ubicación actual» of a zone's form: asks the browser for its position only when RH taps it, hands the reading
 * over, and says how precise it was or why there is none.
 */
export function useZoneLocation(onFound: (reading: Reading) => void) {
    const [status, setStatus] = useState<Status>({
        busy: false,
        text: null,
        failed: false,
    });

    const locate = async () => {
        setStatus({ busy: true, text: null, failed: false });
        const result = await currentPosition(navigator.geolocation);

        if (result.ok) {
            onFound(result.position);
            setStatus({
                busy: false,
                text: accuracyText(result.position.accuracy),
                failed: false,
            });

            return;
        }

        setStatus({
            busy: false,
            text: locationErrorText(result.error),
            failed: true,
        });
    };

    return { ...status, locate };
}
