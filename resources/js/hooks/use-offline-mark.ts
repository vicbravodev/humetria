import { useState } from 'react';
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
 * while `busy`. `mark` gives the result too, for the screen that called it to act on at once.
 */
export function useOfflineMark(slug: string) {
    const store = useOfflineStore(slug);
    const [phase, setPhase] = useState<'signing' | null>(null);
    const [result, setResult] = useState<PreparedMark | null>(null);

    const mark = async (input: MarkInput): Promise<PreparedMark> => {
        if (store === null) {
            const notReady: PreparedMark = {
                kind: 'not-ready',
                why: 'no-context',
            };
            setResult(notReady);

            return notReady;
        }

        setPhase('signing');
        const prepared = await prepareMark(input, {
            store,
            getKey,
            subtle: crypto.subtle,
            now: Date.now,
        });
        setPhase(null);
        setResult(prepared);
        announceStoreChange();

        return prepared;
    };

    return { mark, phase, result, busy: phase !== null };
}
