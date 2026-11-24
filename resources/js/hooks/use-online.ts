import { useSyncExternalStore } from 'react';

function subscribe(onChange: () => void): () => void {
    window.addEventListener('online', onChange);
    window.addEventListener('offline', onChange);

    return () => {
        window.removeEventListener('online', onChange);
        window.removeEventListener('offline', onChange);
    };
}

/** Whether the browser says it has a network. It can say yes with no internet behind it: only a hint, never evidence. */
export function useOnline(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );
}
