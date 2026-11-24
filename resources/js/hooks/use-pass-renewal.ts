import { useEffect } from 'react';
import {
    announceStoreChange,
    useOfflineStore,
} from '@/hooks/use-offline-store';
import { xsrfToken } from '@/lib/assistant/xsrf';
import { renewPass } from '@/lib/pwa/renew-pass';
import { pass } from '@/routes/worker/device-key';

/**
 * Renews the pass of the device key when the person opens their start: the one page with a session. Without it the pass
 * (24 hours) would run out and plan B would stop signing. No effect on a phone that has no device key.
 */
export function usePassRenewal(slug: string): void {
    const store = useOfflineStore(slug);

    useEffect(() => {
        if (store === null) {
            return;
        }

        void renewPass(store, pass.url(slug), {
            fetcher: fetch.bind(window),
            xsrf: xsrfToken(document.cookie),
        }).then(announceStoreChange);
    }, [store, slug]);
}
