import { useEffect, useState } from 'react';
import {
    announceStoreChange,
    useOfflineStore,
} from '@/hooks/use-offline-store';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { deviceLabel } from '@/lib/webauthn';
import { registerDeviceKey } from '@/lib/worker-access/device-key-registration';

export type DeviceKeyState =
    | 'idle'
    | 'working'
    | 'ready'
    | 'proof'
    | 'session'
    | 'failed';

/**
 * Prepares this phone to sign marks without a connection when the person's own key does not (plan B): makes the device key,
 * registers it with the server and keeps it, with its pass, in the phone. `proof` means the server asked for a recent proof
 * first: the card opens it and calls `register` again from the start (a new challenge). A phone already prepared starts as
 * `ready`.
 */
export function useDeviceKey({
    slug,
    optionsUrl,
    storeUrl,
}: {
    slug: string;
    optionsUrl: string;
    storeUrl: string;
}) {
    const store = useOfflineStore(slug);
    const [state, setState] = useState<DeviceKeyState>('idle');

    useEffect(() => {
        if (store === null) {
            return undefined;
        }

        let alive = true;

        void Promise.all([store.deviceKey(), store.pass()]).then(
            ([key, pass]) => {
                if (alive && key !== null && pass !== null) {
                    setState((now) => (now === 'idle' ? 'ready' : now));
                }
            },
        );

        return () => {
            alive = false;
        };
    }, [store]);

    const register = async () => {
        if (store === null) {
            setState('failed');

            return;
        }

        setState('working');
        const outcome = await registerDeviceKey(
            { optionsUrl, storeUrl },
            {
                fetcher: fetch.bind(window),
                xsrf: xsrfToken(document.cookie),
                subtle: crypto.subtle,
                label: deviceLabel(navigator.userAgent),
            },
        );

        if (outcome.kind !== 'ready') {
            setState(outcome.kind);

            return;
        }

        await store.saveDeviceKey(outcome.key);
        await store.savePass({
            deviceKeyId: outcome.deviceKeyId,
            pass: outcome.pass,
            passId: outcome.passId,
            passExpiresAt: outcome.passExpiresAt,
        });
        announceStoreChange();
        setState('ready');
    };

    return { state, register, close: () => setState('idle') };
}
