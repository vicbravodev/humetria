import { isMarkType } from '@/lib/attendance/next-step';
import type { MarkType } from '@/lib/attendance/next-step';

/*
 * The marks a phone made without a connection and has not delivered, and what becomes of each when the server answers.
 * Pure: the phone's storage is `offline-store.ts`; the delivery itself, `delivery.ts`.
 */

export const QUEUE_SCHEMA = 1;

type Json = Record<string, unknown>;

/** One mark as the contract of the delivery says it (docs/superpowers/specs/2026-11-20-worker-pwa-offline-design.md). */
export type DeliveryItem = {
    seq: number;
    type: MarkType;
    ticket: string;
    center_id: string;
    qr?: string;
    /** Unix seconds. */
    declared_at: number;
    lat_e5?: number;
    lng_e5?: number;
    accuracy_m?: number;
    device_clock_at?: number;
    justification?: string;
    agreement_version: number;
    signer: 'passkey' | 'device_key';
    credential?: Json;
    device_key_id?: string;
    signature?: string;
    pass?: string;
};

/**
 * A mark of the queue. `queued` waits for its delivery; `kept` is one the server did not accept and stays in the phone
 * until the person says they understood. There is no «sending» state: the delivery under way is a flag in memory, and a
 * mark whose delivery was cut is still `queued` (the server answers `duplicate` the second time).
 */
export type QueuedMark = {
    schema: number;
    seq: number;
    employeeId: string;
    /** The phone's clock: only to show and to age the queue, never evidence. */
    createdAt: number;
    item: DeliveryItem;
    state: 'queued' | 'kept';
    reason: string | null;
};

/** What the server said of one mark. */
export type DeliveryResult = {
    seq: number;
    status: string;
    reason?: string;
    stored_status?: string;
};

/** What the person is told once after a delivery, with the mark already out of the queue. */
export type QueueNotice = {
    seq: number;
    employeeId: string;
    kind: 'received' | 'already_recorded';
};

export type Applied = {
    /** Marks that changed and must be written again. */
    puts: QueuedMark[];
    /** The `seq` of the marks that leave the queue. */
    removed: number[];
    notices: QueueNotice[];
};

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isItem(value: unknown): value is DeliveryItem {
    return (
        isJson(value) &&
        typeof value['seq'] === 'number' &&
        isMarkType(value['type']) &&
        typeof value['ticket'] === 'string' &&
        typeof value['center_id'] === 'string' &&
        typeof value['declared_at'] === 'number' &&
        typeof value['agreement_version'] === 'number' &&
        (value['signer'] === 'passkey' || value['signer'] === 'device_key')
    );
}

function isMark(value: unknown): value is QueuedMark {
    return (
        isJson(value) &&
        typeof value['seq'] === 'number' &&
        typeof value['employeeId'] === 'string' &&
        typeof value['createdAt'] === 'number' &&
        (value['state'] === 'queued' || value['state'] === 'kept') &&
        (value['reason'] === null || typeof value['reason'] === 'string') &&
        isItem(value['item'])
    );
}

/**
 * A mark read from the store: the mark, `newer` when it was written by a later schema (a tab with an old bundle must
 * neither deliver, delete nor rewrite it) or null when it is damaged. Nothing is forced.
 */
export function readMark(value: unknown): QueuedMark | 'newer' | null {
    const schema = isJson(value) ? value['schema'] : null;

    if (typeof schema !== 'number' || !Number.isInteger(schema) || schema < 1) {
        return null;
    }

    if (schema > QUEUE_SCHEMA) {
        return 'newer';
    }

    return isMark(value) ? value : null;
}

/** The marks of one person: the screen counts and lists only theirs (the delivery carries everyone's). */
export function forPerson(
    marks: QueuedMark[],
    employeeId: string | null,
): QueuedMark[] {
    return marks.filter((mark) => mark.employeeId === employeeId);
}

function bySeq(marks: QueuedMark[]): QueuedMark[] {
    return [...marks].sort((a, b) => a.seq - b.seq);
}

export function pending(marks: QueuedMark[]): QueuedMark[] {
    return bySeq(marks.filter((mark) => mark.state === 'queued'));
}

export function kept(marks: QueuedMark[]): QueuedMark[] {
    return bySeq(marks.filter((mark) => mark.state === 'kept'));
}

/** The mark refused, with its reason; it stays in the phone. */
export function keep(mark: QueuedMark, reason: string | null): QueuedMark {
    return { ...mark, schema: QUEUE_SCHEMA, state: 'kept', reason };
}

/** The server has the mark: it received it, or already had it from before. */
function stored(result: DeliveryResult): boolean {
    return (
        result.status === 'received' ||
        (result.status === 'duplicate' && result.stored_status === 'received')
    );
}

/** The marks have the server's answer for each one: what leaves, what is kept and what to tell the person. */
export function apply(marks: QueuedMark[], results: DeliveryResult[]): Applied {
    const applied: Applied = { puts: [], removed: [], notices: [] };

    for (const result of results) {
        const mark = marks.find(
            (candidate) =>
                candidate.seq === result.seq && candidate.state === 'queued',
        );

        if (mark === undefined) {
            continue;
        }

        if (stored(result)) {
            applied.removed.push(mark.seq);
            applied.notices.push({
                seq: mark.seq,
                employeeId: mark.employeeId,
                kind:
                    result.reason === 'already_recorded'
                        ? 'already_recorded'
                        : 'received',
            });
        } else if (
            ['rejected', 'conflict', 'duplicate'].includes(result.status)
        ) {
            applied.puts.push(keep(mark, result.reason ?? null));
        }
    }

    return applied;
}
