import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Closes the phone's whole menu (the sidebar's sheet) as soon as the person heads to another page from it, so it slides
 * out while the new page comes in. A partial reload or a submission (a poll, a filter) leaves it as it is.
 */
export function useCloseMenuOnVisit(close: () => void): void {
    useEffect(
        () =>
            router.on('start', (event) => {
                const { method, only } = event.detail.visit;

                if (method === 'get' && only.length === 0) {
                    close();
                }
            }),
        [close],
    );
}
