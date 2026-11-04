import type { FollowUp, FollowUpEntry } from '@/types';

const STATUSES = ['received', 'in_review', 'closed'] as const;

function has<K extends string>(
    value: unknown,
    key: K,
): value is Record<K, unknown> {
    return typeof value === 'object' && value !== null && key in value;
}

function isStatus(value: unknown): value is FollowUp['status'] {
    return STATUSES.some((status) => status === value);
}

function isSender(value: unknown): value is FollowUpEntry['from'] {
    return value === 'reporter' || value === 'handlers';
}

function isEntry(value: unknown): value is FollowUpEntry {
    return (
        has(value, 'from') &&
        has(value, 'text') &&
        has(value, 'day') &&
        isSender(value.from) &&
        typeof value.text === 'string' &&
        (value.day === null || typeof value.day === 'string')
    );
}

function isPage(value: unknown): value is FollowUp['entries'] {
    return (
        has(value, 'data') &&
        has(value, 'current_page') &&
        has(value, 'last_page') &&
        has(value, 'total') &&
        Array.isArray(value.data) &&
        value.data.every(isEntry) &&
        typeof value.current_page === 'number' &&
        typeof value.last_page === 'number' &&
        typeof value.total === 'number'
    );
}

type Summary = Pick<
    FollowUp,
    'status' | 'statusLabel' | 'resolution' | 'handlers'
>;

function readSummary(value: unknown): Summary | undefined {
    if (
        has(value, 'status') &&
        has(value, 'statusLabel') &&
        has(value, 'resolution') &&
        has(value, 'handlers') &&
        isStatus(value.status) &&
        typeof value.statusLabel === 'string' &&
        (value.resolution === null || typeof value.resolution === 'string') &&
        typeof value.handlers === 'number'
    ) {
        return {
            status: value.status,
            statusLabel: value.statusLabel,
            resolution: value.resolution,
            handlers: value.handlers,
        };
    }

    return undefined;
}

type Flags = Pick<FollowUp, 'canReply' | 'canWithdraw'>;

function readFlags(value: unknown): Flags | undefined {
    if (
        has(value, 'canReply') &&
        has(value, 'canWithdraw') &&
        typeof value.canReply === 'boolean' &&
        typeof value.canWithdraw === 'boolean'
    ) {
        return { canReply: value.canReply, canWithdraw: value.canWithdraw };
    }

    return undefined;
}

/** What the server answers to a follow-up, checked; undefined when it is anything else. */
export function parseFollowUp(value: unknown): FollowUp | undefined {
    const summary = readSummary(value);
    const flags = readFlags(value);

    if (
        summary === undefined ||
        flags === undefined ||
        !has(value, 'entries') ||
        !isPage(value.entries)
    ) {
        return undefined;
    }

    return { ...summary, ...flags, entries: value.entries };
}

/** Who wrote an entry, as the person reads it: never a name. */
export function senderLabel(from: FollowUpEntry['from']): string {
    return from === 'reporter' ? 'Usted' : 'Persona encargada';
}
