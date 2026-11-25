import type { KeyValueStore } from '@/lib/pwa/offline-store';

/**
 * A store in memory for the tests of the phone's storage: the same three operations IndexedDB gives (`get`, `entries` and
 * a `transact` that applies everything or nothing). `failNext()` makes the next `transact` fail before it writes anything.
 */
export function memoryStore(): KeyValueStore & {
    data: Map<string, unknown>;
    failNext: () => void;
} {
    const data = new Map<string, unknown>();
    let failing = false;

    return {
        data,
        failNext: () => {
            failing = true;
        },
        get: (key) => Promise.resolve(data.get(key)),
        entries: (prefix) =>
            Promise.resolve(
                [...data.entries()]
                    .filter(([key]) => key.startsWith(prefix))
                    .sort(([a], [b]) => a.localeCompare(b)),
            ),
        transact: (puts, deletes) => {
            if (failing) {
                failing = false;

                return Promise.reject(new Error('The write failed.'));
            }

            for (const [key, value] of puts) {
                data.set(key, value);
            }

            for (const key of deletes) {
                data.delete(key);
            }

            return Promise.resolve();
        },
    };
}
