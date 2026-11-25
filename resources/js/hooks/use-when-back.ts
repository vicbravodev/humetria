import { useEffect } from 'react';

/** Calls `action` when the network comes back and when the app returns to the front. */
export function useWhenBack(action: () => Promise<void>): void {
    useEffect(() => {
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void action();
            }
        };
        const onOnline = () => void action();

        window.addEventListener('online', onOnline);
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.removeEventListener('online', onOnline);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [action]);
}
