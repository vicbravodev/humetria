/*
 * The tickets the phone keeps to mark without a connection, the context that comes with them and the pass of the device key.
 * Every answer the server signs after a verified signature carries `{tickets, context}`; this reads them. Pure.
 */

/** Two batches of eight: what the phone keeps. */
export const MAX_TICKETS = 16;

/** The least time between two requests for tickets. */
const RENEWAL_GAP_MS = 30_000;

export type Ticket = { ticket: string; issuedAt: number };

export type OfflineContext = {
    tenantId: string;
    employeeId: string;
    centerId: string;
    /** The version of the agreement in force that the person accepted; null when they have not. */
    agreementVersion: number | null;
    rpId: string;
    /** The company's switch: the only one the phone reads. */
    offline: boolean;
};

/** The pass of a device key: the server's text, its id, the key's id and when it expires (an ISO 8601 date). */
export type Pass = {
    deviceKeyId: string;
    pass: string;
    passId: string;
    passExpiresAt: string;
};

type Json = Record<string, unknown>;

function isJson(value: unknown): value is Json {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function ticketOf(value: unknown): Ticket[] {
    return isJson(value) &&
        typeof value['ticket'] === 'string' &&
        value['ticket'] !== '' &&
        typeof value['issuedAt'] === 'number'
        ? [{ ticket: value['ticket'], issuedAt: value['issuedAt'] }]
        : [];
}

/** The tickets of an answer; the entries it cannot read are left out. */
export function ticketsIn(json: unknown): Ticket[] {
    return isJson(json) && Array.isArray(json['tickets'])
        ? json['tickets'].flatMap(ticketOf)
        : [];
}

/** The context of an answer; null when it carries none or it is damaged. */
export function contextIn(json: unknown): OfflineContext | null {
    const context = isJson(json) ? json['context'] : null;

    if (
        !isJson(context) ||
        typeof context['tenantId'] !== 'string' ||
        typeof context['employeeId'] !== 'string' ||
        typeof context['centerId'] !== 'string' ||
        typeof context['rpId'] !== 'string' ||
        typeof context['offline'] !== 'boolean'
    ) {
        return null;
    }

    const version = context['agreementVersion'];

    return version === null || typeof version === 'number'
        ? {
              tenantId: context['tenantId'],
              employeeId: context['employeeId'],
              centerId: context['centerId'],
              agreementVersion: version,
              rpId: context['rpId'],
              offline: context['offline'],
          }
        : null;
}

/** The pass of a registration (201) or a renewal (200), which have the same shape; null when it is partial. */
export function passIn(json: unknown): Pass | null {
    return isJson(json) &&
        typeof json['deviceKeyId'] === 'string' &&
        typeof json['pass'] === 'string' &&
        typeof json['passId'] === 'string' &&
        typeof json['passExpiresAt'] === 'string'
        ? {
              deviceKeyId: json['deviceKeyId'],
              pass: json['pass'],
              passId: json['passId'],
              passExpiresAt: json['passExpiresAt'],
          }
        : null;
}

/** What is stored and what came, without repeats, the newest first, at most `max`. The phone does not prune by age. */
export function merge(
    stored: Ticket[],
    incoming: Ticket[],
    max = MAX_TICKETS,
): Ticket[] {
    const seen = new Set<string>();
    const unique = [...stored, ...incoming].filter(({ ticket }) => {
        const fresh = !seen.has(ticket);
        seen.add(ticket);

        return fresh;
    });

    return unique.sort((a, b) => b.issuedAt - a.issuedAt).slice(0, max);
}

/** The ticket a mark spends: the newest that is left, the tightest lower bound. */
export function newest(tickets: Ticket[]): Ticket | null {
    return tickets[0] ?? null;
}

/** The tickets that are left once one is spent. */
export function without(tickets: Ticket[], used: string): Ticket[] {
    return tickets.filter(({ ticket }) => ticket !== used);
}

/** `none` (0: it cannot mark without a connection), `low` (1 or 2: it warns) or `ok`. */
export function stock(tickets: Ticket[]): 'none' | 'low' | 'ok' {
    if (tickets.length === 0) {
        return 'none';
    }

    return tickets.length <= 2 ? 'low' : 'ok';
}

/** Whether 30 seconds went by since the last request for tickets (milliseconds); `null` when there was none. */
export function renewalDue(lastAt: number | null, now: number): boolean {
    return lastAt === null || now - lastAt >= RENEWAL_GAP_MS;
}

/** Whether the phone's clock says the pass is still good: only to warn, the server compares against the declared hour. */
export function passUsable(pass: Pass | null, now: number): boolean {
    return pass !== null && Date.parse(pass.passExpiresAt) > now;
}
