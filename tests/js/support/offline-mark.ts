import { vi } from 'vitest';
import type { MarkInput } from '@/lib/pwa/offline-mark';
import { offlineStore } from '@/lib/pwa/offline-store';
import type { SignedKey } from '@/lib/webauthn';
import { memoryStore } from './memory-store';

/** What the tests of the phone's own marks share: a person in a company with tickets, a device that signs, a clock. */

export const context = {
    tenantId: 'tenant-1',
    employeeId: 'ana',
    centerId: 'center-1',
    agreementVersion: 3,
    rpId: 'humetria.test',
    offline: true,
    qrTypes: ['start', 'end'],
};

export const NOW = Date.parse('2026-11-25T09:00:30Z');

export const key: SignedKey = {
    rawId: new Uint8Array([9]).buffer,
    type: 'public-key',
    response: {
        clientDataJSON: new Uint8Array([1]).buffer,
        authenticatorData: new Uint8Array([2]).buffer,
        signature: new Uint8Array([3]).buffer,
        userHandle: new Uint8Array([4]).buffer,
    },
};

export const input: MarkInput = {
    type: 'start',
    qr: null,
    position: { latE5: 1916543, lngE5: -9913270, accuracyM: 12 },
    declaredAt: 1_794_050_400,
    justification: null,
    useDeviceKey: false,
};

export const signs = vi.fn((options: PublicKeyCredentialRequestOptions) => {
    void options;

    return Promise.resolve(key);
});

export const refuses = () =>
    Promise.reject(new DOMException('no', 'NotAllowedError'));

/** A store with a context and `n` tickets; the newest has the highest issuedAt. */
export async function readyStore(n = 3, over: Partial<typeof context> = {}) {
    const kv = memoryStore();
    const store = offlineStore(kv);
    await store.saveContext({ ...context, ...over });
    await store.saveTickets(
        Array.from({ length: n }, (_, i) => ({
            ticket: `v1.t${i}.mac`,
            issuedAt: 100 + i,
        })).reverse(),
    );

    return { kv, store };
}

export function depsOf(
    store: ReturnType<typeof offlineStore>,
    getKey: (
        options: PublicKeyCredentialRequestOptions,
    ) => Promise<SignedKey | null> = signs,
) {
    return { store, getKey, subtle: crypto.subtle, now: () => NOW };
}
