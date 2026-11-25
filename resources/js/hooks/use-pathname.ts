import { useSyncExternalStore } from 'react';

function subscribe(onChange: () => void): () => void {
    window.addEventListener('popstate', onChange);

    return () => window.removeEventListener('popstate', onChange);
}

/** The path of the page, read again whenever the history moves: for a page that is not Inertia's (the app's shell). */
export function usePathname(): string {
    return useSyncExternalStore(
        subscribe,
        () => location.pathname,
        () => '/',
    );
}
