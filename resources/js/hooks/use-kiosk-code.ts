import { useEffect, useState } from 'react';
import type { KioskCode } from '@/lib/attendance/kiosk';
import { clockOffset } from '@/lib/attendance/punch-view';
import {
    fetchKioskCode,
    refreshDelay,
    retryDelay,
} from '@/lib/attendance/kiosk';

/** The code on the screen and when it came (this device's clock, in ms). */
type Shown = { code: KioskCode; at: number };

/**
 * The current code of a QR screen, asked for again as each one turns. Without a usable answer it drops the code it held (a
 * code on the wall that no longer turns must not stay), says so, remembers when the last good one came and retries, waiting
 * longer each time; `retry` asks at once. Each answer also says how far this device's clock is from the server's (`offset`,
 * in ms; `initialOffset`, from the page, until the first one). A screen RH revoked gets a 404 and reloads, to show the page of a link that no
 * longer works. The screen's credential travels in its httpOnly cookie, never through this code.
 */
export function useKioskCode(endpoint: string, initialOffset: number) {
    const [shown, setShown] = useState<Shown | null>(null);
    const [offset, setOffset] = useState(initialOffset);
    const [online, setOnline] = useState(true);
    const [lastValidAt, setLastValidAt] = useState<number | null>(null);
    const [attempt, setAttempt] = useState(0);

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

            if (answer === null) {
                setShown(null);
            } else {
                const at = Date.now();
                setShown({ code: answer, at });
                setLastValidAt(at);
                setOffset(clockOffset(answer.now, at));
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
    }, [endpoint, attempt]);

    return {
        code: shown?.code ?? null,
        receivedAt: shown?.at ?? 0,
        online,
        lastValidAt,
        offset,
        retry: () => setAttempt((count) => count + 1),
    };
}
