import { useSyncExternalStore } from 'react';

const subscribe = (callback: () => void) => {
    window.addEventListener('scroll', callback, { passive: true });

    return () => window.removeEventListener('scroll', callback);
};

/** Whether the page has scrolled past `offset` pixels; false while rendering on the server. */
export function useScrolled(offset = 4): boolean {
    return useSyncExternalStore(
        subscribe,
        () => window.scrollY > offset,
        () => false,
    );
}
