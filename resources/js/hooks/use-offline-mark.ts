import { useRef, useState } from 'react';
import {
    announceStoreChange,
    useOfflineStore,
} from '@/hooks/use-offline-store';
import { getKey } from '@/hooks/use-punch';
import { prepareMark } from '@/lib/pwa/offline-mark';
import type { MarkInput, PreparedMark } from '@/lib/pwa/offline-mark';

/**
 * Makes a mark without a connection: spends a ticket, has the passkey (or the device key) sign and keeps the mark in the
 * phone's queue. `phase` is `signing` while the person confirms with their fingerprint, face or PIN; the button stays off
 * while `busy`. `mark` gives the result too, for the screen that called it to act on at once, and nothing (null) for a second
 * call while one is under way: a double tap would read the same ticket and number twice and ask for two signatures.
 */
export function useOfflineMark(slug: string) {
    const store = useOfflineStore(slug);
    const [phase, setPhase] = useState<'signing' | null>(null);
    const [result, setResult] = useState<PreparedMark | null>(null);
    const running = useRef(false);

    const mark = async (input: MarkInput): Promise<PreparedMark | null> => {
        if (running.current) {
            return null;
        }

        if (store === null) {
            const notReady: PreparedMark = {
                kind: 'not-ready',
                why: 'no-context',
            };
            setResult(notReady);

            return notReady;
        }

        running.current = true;
        setPhase('signing');
        const prepared = await prepareMark(input, {
            store,
            getKey,
            subtle: crypto.subtle,
            now: Date.now,
        }).finally(() => {
            running.current = false;
        });
        setPhase(null);
        setResult(prepared);
        announceStoreChange();

        return prepared;
    };

    return { mark, phase, result, busy: phase !== null };
}
