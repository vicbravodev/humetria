import { isRecord, optional, parseQuote } from '@/lib/billing-preview';
import type { TransferEffect, TransferPreview } from '@/types';

function parseAccount(value: unknown): { id: string; name: string } | null {
    return isRecord(value) &&
        typeof value['id'] === 'string' &&
        typeof value['name'] === 'string'
        ? { id: value['id'], name: value['name'] }
        : null;
}

function parseProfile(
    value: unknown,
): { before: string; after: string } | null {
    return isRecord(value) &&
        typeof value['before'] === 'string' &&
        typeof value['after'] === 'string'
        ? { before: value['before'], after: value['after'] }
        : null;
}

function isRole(value: unknown): value is TransferEffect['role'] {
    return value === 'from' || value === 'to';
}

function isEffect(value: unknown): value is TransferEffect['effect'] {
    return value === 'loses' || value === 'gains' || value === 'same';
}

function parseEffect(value: unknown): TransferEffect | null {
    if (!isRecord(value)) {
        return null;
    }

    const { role, account, before, after, effect, text } = value;

    return isRole(role) &&
        isEffect(effect) &&
        typeof account === 'string' &&
        typeof before === 'number' &&
        typeof after === 'number' &&
        typeof text === 'string'
        ? { role, account, before, after, effect, text }
        : null;
}

function parseEffects(value: unknown): TransferEffect[] | null {
    if (!Array.isArray(value)) {
        return null;
    }

    const effects = value.map(parseEffect);

    return effects.every((effect) => effect !== null) ? effects : null;
}

/** The server's answer to a move being typed, or null when it is not one. */
export function parseTransferPreview(value: unknown): TransferPreview | null {
    if (!isRecord(value)) {
        return null;
    }

    const from = parseAccount(value['from']);
    const to = optional(value['to'], parseAccount);
    const current = optional(value['current'], parseQuote);
    const quote = optional(value['quote'], parseQuote);
    const profile = optional(value['profile'], parseProfile);
    const effects = parseEffects(value['effects']);
    const { effective_on: effectiveOn, access } = value;

    return from === null ||
        to === undefined ||
        current === undefined ||
        quote === undefined ||
        profile === undefined ||
        effects === null ||
        typeof effectiveOn !== 'string' ||
        typeof access !== 'string'
        ? null
        : {
              from,
              to,
              effective_on: effectiveOn,
              current,
              quote,
              profile,
              effects,
              access,
          };
}

/** "De $2,490 a $3,990": what the company pays now against what it would pay with the new payer; null when it is the same or either needs a quotation. */
export function transferPriceChange(preview: TransferPreview): string | null {
    const from = preview.current?.subtotal;
    const to = preview.quote?.subtotal;

    if (
        from === null ||
        from === undefined ||
        to === null ||
        to === undefined ||
        from === to
    ) {
        return null;
    }

    return `De ${from} a ${to}`;
}

/** The next cut after today ("2026-11-10" → "2026-12-01"): the first day a move can take effect without touching a month already billed. */
export function nextCut(today: string): string {
    const [year = 0, month = 1] = today.split('-').map(Number);
    const next = new Date(Date.UTC(year, month, 1));

    return next.toISOString().slice(0, 10);
}
