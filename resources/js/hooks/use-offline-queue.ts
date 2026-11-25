import { useCallback, useEffect, useRef, useState } from 'react';
import {
    announceStoreChange,
    useOfflineStore,
    useStoreChanged,
} from '@/hooks/use-offline-store';
import { useWhenBack } from '@/hooks/use-when-back';
import type { MarkType } from '@/lib/attendance/next-step';
import { postJson } from '@/lib/pwa/post';
import { runDelivery } from '@/lib/pwa/run-delivery';
import { forPerson, kept, pending } from '@/lib/pwa/queue';
import type { QueueNotice, QueuedMark } from '@/lib/pwa/queue';
import type { OfflineContext } from '@/lib/pwa/tickets';

/** How long one delivery request may take before the attempt is given up. */
const REQUEST_TIMEOUT_MS = 10_000;

/** How long «Recibida» stays on screen. */
const NOTICE_MS = 10_000;

/** A mark the server did not accept: it stays in the phone until the person understood or asked for a correction. */
export type KeptMark = {
    seq: number;
    type: MarkType;
    /** Unix seconds: the hour the person said. */
    declaredAt: number;
    reason: string | null;
    dismiss: () => Promise<void>;
};

/** What the last delivery told the person, for a few seconds. */
function useNotices() {
    const [notices, setNotices] = useState<QueueNotice[]>([]);

    useEffect(() => {
        if (notices.length === 0) {
            return undefined;
        }

        const timeout = setTimeout(() => setNotices([]), NOTICE_MS);

        return () => clearTimeout(timeout);
    }, [notices]);

    return [notices, setNotices] as const;
}

/**
 * The queue of marks made without a connection, for the person on the screen: how many wait, the ones the server refused
 * (with their own `dismiss`) and what the last delivery said. The delivery carries everyone's marks and runs on its own: on
 * opening, when the network returns, when the app comes to the front, and at the time it was told to wait. One at a time per
 * tab; between tabs the server's idempotence covers a repeated mark.
 */
export function useOfflineQueue({
    slug,
    deliverUrl,
}: {
    slug: string;
    deliverUrl: string;
}) {
    const store = useOfflineStore(slug);
    const [view, setView] = useState<{
        marks: QueuedMark[];
        context: OfflineContext | null;
    }>({ marks: [], context: null });
    const [notices, setNotices] = useNotices();
    const running = useRef(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const again = useRef<() => Promise<void>>(() => Promise.resolve());

    const [version, setVersion] = useState(0);

    // What the storage holds, read again each time it changes (a mark, a delivery, a ticket).
    useEffect(() => {
        if (store === null) {
            return undefined;
        }

        let alive = true;

        void Promise.all([store.marks(), store.context()]).then(
            ([marks, context]) => {
                if (alive) {
                    setView({ marks, context });
                }
            },
        );

        return () => {
            alive = false;
        };
    }, [store, version]);

    const deliver = useCallback(async () => {
        if (store === null || running.current) {
            return;
        }

        running.current = true;

        try {
            const done = await runDelivery(store, {
                post: (payload) =>
                    postJson(deliverUrl, payload, REQUEST_TIMEOUT_MS),
                now: Date.now,
            });

            if (done.notices.length > 0) {
                setNotices(done.notices);
            }

            if (timer.current !== null) {
                clearTimeout(timer.current);
            }

            timer.current =
                done.nextAt === null
                    ? null
                    : setTimeout(
                          () => void again.current(),
                          Math.max(0, done.nextAt - Date.now()),
                      );
        } finally {
            running.current = false;
            announceStoreChange();
        }
    }, [store, deliverUrl, setNotices]);

    useEffect(() => {
        again.current = deliver;
        void deliver();

        return () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        };
    }, [deliver]);

    useStoreChanged(useCallback(() => setVersion((n) => n + 1), []));
    useWhenBack(deliver);

    const person = view.context?.employeeId ?? null;
    const mine = forPerson(view.marks, person);

    return {
        pending: pending(mine).length,
        kept: kept(mine).map((mark): KeptMark => ({
            seq: mark.seq,
            type: mark.item.type,
            declaredAt: mark.item.declared_at,
            reason: mark.reason,
            dismiss: async () => {
                await store?.discard(mark.seq);
                announceStoreChange();
            },
        })),
        notices: notices.filter((notice) => notice.employeeId === person),
        deliver,
    };
}
