import { useEffect, useState } from 'react';
import type { KioskCode } from '@/lib/attendance/kiosk';
import {
    parseKioskCode,
    refreshDelay,
    retryDelay,
} from '@/lib/attendance/kiosk';

/**
 * The current code of a QR screen, asked for again as each one turns. Without a connection it keeps the last code and
 * retries, waiting longer each time; a screen RH revoked gets a 404 and reloads, to show the page of a link that no longer works.
 * The screen's token travels in its httpOnly cookie, never through this code.
 */
export function useKioskCode(endpoint: string) {
    const [code, setCode] = useState<KioskCode | null>(null);
    const [online, setOnline] = useState(true);

    useEffect(() => {
        let timer: number | undefined;
        let failures = 0;
        let active = true;

        const schedule = (delay: number) => {
            if (active) {
                timer = window.setTimeout(() => void load(), delay);
            }
        };

        const load = async () => {
            const next = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            }).then(
                async (response) => {
                    if (response.status === 404) {
                        window.location.reload();
                    }

                    return response.ok
                        ? parseKioskCode(await response.json())
                        : null;
                },
                () => null,
            );

            if (!active) {
                return;
            }

            failures = next === null ? failures + 1 : 0;
            setOnline(next !== null);

            if (next !== null) {
                setCode(next);
            }

            schedule(
                next === null
                    ? retryDelay(failures)
                    : refreshDelay(next.expiresIn),
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
