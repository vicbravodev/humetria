import { useEffect, useState } from 'react';
import type { KioskCode } from '@/lib/attendance/kiosk';
import {
    fetchKioskCode,
    refreshDelay,
    retryDelay,
} from '@/lib/attendance/kiosk';

/**
 * The current code of a QR screen, asked for again as each one turns. Without a usable answer it keeps the last code, says
 * so and retries, waiting longer each time; a screen RH revoked gets a 404 and reloads, to show the page of a link that no
 * longer works. The screen's credential travels in its httpOnly cookie, never through this code.
 */
export function useKioskCode(endpoint: string) {
    const [code, setCode] = useState<KioskCode | null>(null);
    const [online, setOnline] = useState(true);

    useEffect(() => {
        let timer: number | undefined;
        let failures = 0;
        let active = true;

        const load = async () => {
            const answer = await fetchKioskCode(endpoint);

            if (!active) {
                return;
            }

            if (answer === 'gone') {
                window.location.reload();

                return;
            }

            failures = answer === null ? failures + 1 : 0;
            setOnline(answer !== null);

            if (answer !== null) {
                setCode(answer);
            }

            timer = window.setTimeout(
                () => void load(),
                answer === null
                    ? retryDelay(failures)
                    : refreshDelay(answer.expiresIn),
            );
        };

        void load();

        return () => {
            active = false;
            window.clearTimeout(timer);
        };
    }, [endpoint]);

    return { code, online };
}
