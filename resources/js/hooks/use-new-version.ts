import { useEffect, useState } from 'react';
import { watchNewVersion } from '@/lib/pwa/new-version';

/** Whether a newer build of the app is ready (see `watchNewVersion`) and how to load it. */
export function useNewVersion(): { ready: boolean; update: () => void } {
    const [ready, setReady] = useState(false);

    useEffect(
        () =>
            watchNewVersion(
                'serviceWorker' in navigator
                    ? navigator.serviceWorker
                    : undefined,
                () => setReady(true),
            ),
        [],
    );

    return { ready, update: () => location.reload() };
}
