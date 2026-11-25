import type { OfflineStore } from '@/lib/pwa/offline-store';
import { passIn } from '@/lib/pwa/tickets';
import { postJson } from '@/lib/worker-access/http';
import type { HttpDeps } from '@/lib/worker-access/http';

/**
 * Renews the pass of the device key when the person opens their start (the one page with a session): the server gives a new
 * pass of 24 hours for the key the phone holds. A 404 means the key is no longer the person's (RH or «No fui yo» revoked it):
 * the key and its pass go from the phone. Anything else (401, 403, 419, 5xx, no network) changes nothing; the pass simply
 * keeps its date. Without both a key and a pass there is nothing to renew.
 */
export async function renewPass(
    store: OfflineStore,
    url: string,
    http: HttpDeps,
): Promise<void> {
    const pass = await store.pass();

    if (pass === null || (await store.deviceKey()) === null) {
        return;
    }

    try {
        const response = await postJson(
            url,
            { device_key_id: pass.deviceKeyId },
            http,
        );

        if (response.status === 404) {
            await store.dropDeviceKey();

            return;
        }

        const renewed = response.ok ? passIn(await response.json()) : null;

        if (renewed !== null) {
            await store.savePass(renewed);
        }
    } catch {
        // No network or an answer that was not JSON: the pass keeps its date.
    }
}
