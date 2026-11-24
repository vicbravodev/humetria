import { useEffect, useState } from 'react';
import { absorb } from '@/lib/pwa/absorb';
import { offlineStore } from '@/lib/pwa/offline-store';
import type { KeyValueStore, OfflineStore } from '@/lib/pwa/offline-store';
import { readPlatform, shouldAskPersistence } from '@/lib/pwa/platform';

/*
 * The phone's storage for the installed app: one IndexedDB per company (`humetria-me-{slug}`, one object store `kv`, version
 * 1; what evolves is the `schema` field of each record, not the database). It keeps the marks that wait, the tickets, the
 * context, the pass and the device key (a non-extractable CryptoKey, stored by structured clone). Outside Vitest's coverage
 * on purpose: the browser tests cover it. What reads it checks every value (lib/pwa/offline-store.ts).
 */

const STORE = 'kv';
const CHANGED = 'humetria:offline-store';

export const offlineDatabase = (slug: string) => `humetria-me-${slug}`;

function wrap(db: IDBDatabase): KeyValueStore {
    return {
        get: (key) =>
            new Promise((resolve, reject) => {
                const request = db
                    .transaction(STORE)
                    .objectStore(STORE)
                    .get(key);
                request.onsuccess = () => {
                    const value: unknown = request.result;
                    resolve(value);
                };
                request.onerror = () => reject(request.error);
            }),
        entries: (prefix) =>
            new Promise((resolve, reject) => {
                const store = db.transaction(STORE).objectStore(STORE);
                const range = IDBKeyRange.bound(prefix, `${prefix}￿`);
                const keys = store.getAllKeys(range);
                const values = store.getAll(range);
                values.onsuccess = () => {
                    const found: unknown[] = values.result;
                    resolve(
                        keys.result.flatMap((key, index) =>
                            typeof key === 'string'
                                ? [[key, found[index]]]
                                : [],
                        ),
                    );
                };
                values.onerror = () => reject(values.error);
            }),
        transact: (puts, deletes) =>
            new Promise((resolve, reject) => {
                const transaction = db.transaction(STORE, 'readwrite');
                const store = transaction.objectStore(STORE);

                for (const [key, value] of puts) {
                    store.put(value, key);
                }

                for (const key of deletes) {
                    store.delete(key);
                }

                transaction.oncomplete = () => resolve();
                transaction.onerror = () => reject(transaction.error);
                transaction.onabort = () => reject(transaction.error);
            }),
    };
}

export function openIdbStore(name: string): Promise<KeyValueStore> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(name, 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => resolve(wrap(request.result));
        request.onerror = () => reject(request.error);
    });
}

/** Tells every hook that reads the phone's storage that it changed (a delivery, tickets, a mark). */
export function announceStoreChange(): void {
    window.dispatchEvent(new Event(CHANGED));
}

/** Runs `onChange` whenever the storage changed, from this tab. */
export function useStoreChanged(onChange: () => void): void {
    useEffect(() => {
        window.addEventListener(CHANGED, onChange);

        return () => window.removeEventListener(CHANGED, onChange);
    }, [onChange]);
}

/** Asks the browser to keep the app's data, once, when it is installed and has not been granted that. */
async function keepData(): Promise<void> {
    if (!('storage' in navigator) || !('persist' in navigator.storage)) {
        return;
    }

    const persisted = await navigator.storage.persisted();

    if (shouldAskPersistence(readPlatform(window).installed, persisted)) {
        await navigator.storage.persist();
    }
}

/** The company's storage once it is open; null while it opens and for good if the browser does not give one. */
export function useOfflineStore(slug: string): OfflineStore | null {
    const [store, setStore] = useState<OfflineStore | null>(null);

    useEffect(() => {
        let alive = true;

        openIdbStore(offlineDatabase(slug))
            .then((kv) => {
                if (alive) {
                    setStore(offlineStore(kv));
                }

                return keepData();
            })
            .catch(() => undefined);

        return () => {
            alive = false;
        };
    }, [slug]);

    return store;
}

/**
 * «¿No es usted?» and «Salir»: takes the person's tickets, pass, device key, practice and context out of the phone; the marks
 * that wait stay and are still delivered. Nothing is revoked at the server. Best effort, like the rest of the storage.
 */
export async function forgetPerson(slug: string): Promise<void> {
    try {
        const store = offlineStore(await openIdbStore(offlineDatabase(slug)));

        await store.forgetPerson();
        announceStoreChange();
    } catch {
        // No storage: nothing was kept.
    }
}

/**
 * Keeps the tickets and the context that came in an answer the person's signature earned (marking, the week, a correction,
 * the agreement). Best effort: a phone with no storage simply marks online.
 */
export async function storeTicketsFrom(
    json: unknown,
    slug: string,
): Promise<void> {
    try {
        const store = offlineStore(await openIdbStore(offlineDatabase(slug)));

        if (await absorb(store, json)) {
            announceStoreChange();
        }
    } catch {
        // No storage: nothing to keep.
    }
}
