import { useEffect } from 'react';

/**
 * Keeps the screen on while the page is open (a QR screen on a wall): asks for a screen wake lock, and again each time the
 * page is shown, since the browser drops it when the page is hidden. Where the browser has none or refuses, nothing happens.
 */
export function useWakeLock(): void {
    useEffect(() => {
        if (!('wakeLock' in navigator)) {
            return;
        }

        let sentinel: WakeLockSentinel | null = null;
        const request = () => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            navigator.wakeLock.request('screen').then(
                (lock) => {
                    sentinel = lock;
                },
                () => undefined,
            );
        };

        request();
        document.addEventListener('visibilitychange', request);

        return () => {
            document.removeEventListener('visibilitychange', request);
            void sentinel?.release();
        };
    }, []);
}
