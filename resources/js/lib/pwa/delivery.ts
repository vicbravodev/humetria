import { pending } from '@/lib/pwa/queue';
import type { DeliveryResult, QueuedMark } from '@/lib/pwa/queue';
import { contextIn, ticketsIn } from '@/lib/pwa/tickets';
import type { OfflineContext, Ticket } from '@/lib/pwa/tickets';

/*
 * When and how the queue is delivered (`POST /me/{slug}/punch/offline`, the network contract of the design): which marks go
 * together, what each answer means and how long to wait after a failure. Pure; the request itself is the hook's.
 */

/** Marks per delivery: the server's `attendance.offline.delivery_batch_max`. */
export const MAX_BATCH = 20;

const FIRST_DELAY_MS = 5_000;
const LONGEST_DELAY_MS = 900_000;

/** Deliveries and requests for tickets always carry these: JSON in and out, no cookie and no CSRF token. */
export const JSON_HEADERS = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
};

/** When to try again, how many times it failed in a row and how many marks go one by one. */
export type Schedule = {
    nextAttemptAt: number;
    attempts: number;
    isolating: number;
};

export type Outcome =
    /** The server answered each mark it could read. */
    | {
          kind: 'applied';
          results: DeliveryResult[];
          tickets: Ticket[];
          context: OfflineContext | null;
      }
    /** The envelope was refused (422 or 413): the next attempts go one by one. */
    | { kind: 'split'; size: number }
    /** A lone mark the envelope refused: it is the bad one. */
    | { kind: 'malformed' }
    /** Nothing was delivered or nothing is certain: try the whole batch again later. */
    | { kind: 'retry'; retryAfter: number | null };

/**
 * The marks of the next delivery: none before its time; those of one person only (that of the oldest mark: after
 * «¿No es usted?» the queue can hold two people's and the server refuses a mixed envelope); at most 20, or one while a
 * bad mark is being isolated.
 */
export function nextBatch(
    marks: QueuedMark[],
    schedule: Schedule,
    now: number,
): QueuedMark[] {
    const waiting = pending(marks);
    const oldest = waiting[0];

    if (oldest === undefined || now < schedule.nextAttemptAt) {
        return [];
    }

    return waiting
        .filter((mark) => mark.employeeId === oldest.employeeId)
        .slice(0, schedule.isolating > 0 ? 1 : MAX_BATCH);
}

/** The body of the delivery: the marks as the contract says, already signed. `seq` is the phone's own number. */
export function body(batch: QueuedMark[]): { items: QueuedMark['item'][] } {
    return { items: batch.map((mark) => mark.item) };
}

/** How a POST of ours goes: JSON, no cookies. */
export function postInit(payload: unknown): RequestInit {
    return {
        method: 'POST',
        headers: JSON_HEADERS,
        credentials: 'omit',
        body: JSON.stringify(payload),
    };
}

function isJson(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function textOf(value: Record<string, unknown>, key: string) {
    const text = value[key];

    return typeof text === 'string' ? text : undefined;
}

function resultOf(value: unknown): DeliveryResult[] {
    const status = isJson(value) ? textOf(value, 'status') : undefined;

    if (
        !isJson(value) ||
        typeof value['seq'] !== 'number' ||
        status === undefined
    ) {
        return [];
    }

    const reason = textOf(value, 'reason');
    const stored = textOf(value, 'stored_status');

    return [
        {
            seq: value['seq'],
            status,
            ...(reason === undefined ? {} : { reason }),
            ...(stored === undefined ? {} : { stored_status: stored }),
        },
    ];
}

/** The results of a 200; null when the answer has none to read. */
function resultsIn(json: unknown): DeliveryResult[] | null {
    return typeof json === 'object' &&
        json !== null &&
        'results' in json &&
        Array.isArray(json.results)
        ? json.results.flatMap(resultOf)
        : null;
}

/**
 * What an answer of the server (or no answer: `status` null) means. 200 is read mark by mark; a refused envelope (422, 413)
 * splits the batch, and from a batch of one it names that mark as malformed; everything else (403, 404, 429, 5xx, no
 * network) leaves the batch as it was.
 */
export function outcomeOf(
    answer: { status: number | null; json: unknown; retryAfter: number | null },
    batchSize: number,
): Outcome {
    const { status, json, retryAfter } = answer;

    if (status === 200) {
        const results = resultsIn(json);

        return results === null
            ? { kind: 'retry', retryAfter }
            : {
                  kind: 'applied',
                  results,
                  tickets: ticketsIn(json),
                  context: contextIn(json),
              };
    }

    if (status === 422 || status === 413) {
        return batchSize > 1
            ? { kind: 'split', size: batchSize }
            : { kind: 'malformed' };
    }

    return { kind: 'retry', retryAfter };
}

/** The `Retry-After` header in seconds; a date or anything else is ignored. */
export function retryAfterOf(header: string | null): number | null {
    return header !== null && /^\d+$/.test(header) ? Number(header) : null;
}

/** The wait after the n-th failure in a row: 5 s, 10 s, 20 s… up to 15 minutes; a longer `Retry-After` rules. */
export function delayFor(
    attempts: number,
    retryAfterSeconds: number | null,
): number {
    const grown = Math.min(
        FIRST_DELAY_MS * 2 ** Math.max(0, attempts - 1),
        LONGEST_DELAY_MS,
    );

    return Math.max(grown, (retryAfterSeconds ?? 0) * 1000);
}

/** The schedule once an attempt of `batchSize` marks has an outcome. */
export function scheduleAfter(
    schedule: Schedule,
    outcome: Outcome,
    attempt: { size: number; now: number },
): Schedule {
    const { size: batchSize, now } = attempt;

    if (outcome.kind === 'retry') {
        const attempts = schedule.attempts + 1;

        return {
            ...schedule,
            attempts,
            nextAttemptAt: now + delayFor(attempts, outcome.retryAfter),
        };
    }

    return {
        ...schedule,
        attempts: 0,
        nextAttemptAt: now,
        isolating:
            outcome.kind === 'split'
                ? outcome.size
                : Math.max(0, schedule.isolating - batchSize),
    };
}

/** A `fetch` that never got an answer: the network failed or it took too long. */
export function isNetworkFailure(error: unknown): boolean {
    return (
        error instanceof TypeError ||
        (error instanceof DOMException &&
            (error.name === 'TimeoutError' || error.name === 'AbortError'))
    );
}
