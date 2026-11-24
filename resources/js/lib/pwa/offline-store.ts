import { isCryptoKey } from '@/lib/pwa/device-key';
import type { Schedule } from '@/lib/pwa/delivery';
import { readMark } from '@/lib/pwa/queue';
import type { Applied, QueuedMark } from '@/lib/pwa/queue';
import { contextIn, passIn, ticketsIn } from '@/lib/pwa/tickets';
import type { OfflineContext, Pass, Ticket } from '@/lib/pwa/tickets';

/*
 * What the phone keeps to mark without a connection, over a key-value store (IndexedDB in the app: hooks/use-offline-store.ts;
 * a Map in the tests). Everything read goes through a guard: a damaged value is ignored, never forced. One store per
 * company: `humetria-me-{slug}`.
 */

/** The three operations the repository needs; `transact` applies all of its writes or none. */
export type KeyValueStore = {
    get(key: string): Promise<unknown>;
    entries(prefix: string): Promise<[string, unknown][]>;
    transact(puts: [string, unknown][], deletes: string[]): Promise<void>;
};

/** How the signing practice of the sign-up went; only a guide, it never blocks marking. */
export type Practice = {
    result: 'passkey' | 'device_key' | 'failed';
    at: number;
};

const CALM: Schedule = {
    nextAttemptAt: 0,
    attempts: 0,
    isolating: 0,
    lastTicketsAt: null,
};

/** What belongs to the person who was on the phone, and goes when they say «¿No es usted?» or «Salir». */
const THEIRS = ['tickets', 'context', 'pass', 'device-key', 'practice'];

function isJson(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function practiceOf(value: unknown): Practice | null {
    return isJson(value) &&
        (value['result'] === 'passkey' ||
            value['result'] === 'device_key' ||
            value['result'] === 'failed') &&
        typeof value['at'] === 'number'
        ? { result: value['result'], at: value['at'] }
        : null;
}

function scheduleOf(value: unknown): Schedule {
    const last = isJson(value) ? value['lastTicketsAt'] : null;

    return isJson(value) &&
        typeof value['nextAttemptAt'] === 'number' &&
        typeof value['attempts'] === 'number' &&
        typeof value['isolating'] === 'number'
        ? {
              nextAttemptAt: value['nextAttemptAt'],
              attempts: value['attempts'],
              isolating: value['isolating'],
              lastTicketsAt: typeof last === 'number' ? last : null,
          }
        : CALM;
}

const markKey = (seq: number) => `mark/${seq}`;

export function offlineStore(kv: KeyValueStore) {
    return {
        /** The marks that can be read; a record of a newer schema or a damaged one is not here, and nothing touches it. */
        async marks(): Promise<QueuedMark[]> {
            const entries = await kv.entries('mark/');

            return entries.flatMap(([, value]) => {
                const mark = readMark(value);

                return mark === null || mark === 'newer' ? [] : [mark];
            });
        },
        /** A new mark with the tickets that are left and the next `seq`, in one write: either all happen or none. */
        commitMark: (mark: QueuedMark, tickets: Ticket[]) =>
            kv.transact(
                [
                    [markKey(mark.seq), mark],
                    ['tickets', tickets],
                    ['seq', mark.seq + 1],
                ],
                [],
            ),
        /** What a delivery decided: the marks that leave and those that stay refused, in one write. */
        settle: ({ puts, removed }: Applied) =>
            kv.transact(
                puts.map((mark) => [markKey(mark.seq), mark]),
                removed.map(markKey),
            ),
        discard: (seq: number) => kv.transact([], [markKey(seq)]),
        async tickets(): Promise<Ticket[]> {
            return ticketsIn({ tickets: await kv.get('tickets') });
        },
        saveTickets: (tickets: Ticket[]) =>
            kv.transact([['tickets', tickets]], []),
        async context(): Promise<OfflineContext | null> {
            return contextIn({ context: await kv.get('context') });
        },
        saveContext: (context: OfflineContext) =>
            kv.transact([['context', context]], []),
        async pass(): Promise<Pass | null> {
            return passIn(await kv.get('pass'));
        },
        savePass: (pass: Pass) => kv.transact([['pass', pass]], []),
        async deviceKey(): Promise<CryptoKey | null> {
            const key = await kv.get('device-key');

            return isCryptoKey(key) ? key : null;
        },
        saveDeviceKey: (key: CryptoKey) =>
            kv.transact([['device-key', key]], []),
        /** The device key and its pass go together: a pass without its key, or a key without its pass, is of no use. */
        dropDeviceKey: () => kv.transact([], ['device-key', 'pass']),
        async practice(): Promise<Practice | null> {
            return practiceOf(await kv.get('practice'));
        },
        savePractice: (practice: Practice) =>
            kv.transact([['practice', practice]], []),
        /** The number the next mark takes: the phone's own, only to tell the marks apart and to sign. */
        async nextSeq(): Promise<number> {
            const seq = await kv.get('seq');

            return typeof seq === 'number' && Number.isInteger(seq) && seq >= 1
                ? seq
                : 1;
        },
        async schedule(): Promise<Schedule> {
            return scheduleOf(await kv.get('schedule'));
        },
        saveSchedule: (schedule: Schedule) =>
            kv.transact([['schedule', schedule]], []),
        /**
         * «¿No es usted?» and «Salir»: out of the phone go the tickets, the pass, the device key, the practice and the context;
         * the marks that wait stay (they are signed already and need no session). Nothing is revoked at the server.
         */
        forgetPerson: () => kv.transact([], THEIRS),
    };
}

export type OfflineStore = ReturnType<typeof offlineStore>;
