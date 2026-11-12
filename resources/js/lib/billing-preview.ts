import type {
    DiscountNote,
    Quote,
    QuoteLine,
    SubscriptionPreview,
} from '@/types';

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function textOrNull(value: unknown): string | null | undefined {
    return value === null || typeof value === 'string' ? value : undefined;
}

function parseLine(value: unknown): QuoteLine | null {
    if (!isRecord(value)) {
        return null;
    }

    const { label, amount, explanation } = value;

    return typeof label === 'string' &&
        typeof amount === 'string' &&
        typeof explanation === 'string'
        ? { label, amount, explanation }
        : null;
}

function parseLines(value: unknown): QuoteLine[] | null {
    if (!Array.isArray(value)) {
        return null;
    }

    const lines = value.map(parseLine);

    return lines.every((line) => line !== null) ? lines : null;
}

export function parseQuote(value: unknown): Quote | null {
    if (!isRecord(value)) {
        return null;
    }

    const lines = parseLines(value['lines']);
    const subtotal = textOrNull(value['subtotal']);
    const vat = textOrNull(value['vat']);
    const total = textOrNull(value['total']);
    const cents = value['subtotal_cents'];

    if (
        lines === null ||
        typeof value['requires_quotation'] !== 'boolean' ||
        typeof value['explanation'] !== 'string' ||
        subtotal === undefined ||
        vat === undefined ||
        total === undefined ||
        (cents !== null && typeof cents !== 'number')
    ) {
        return null;
    }

    return {
        requires_quotation: value['requires_quotation'],
        explanation: value['explanation'],
        lines,
        subtotal,
        subtotal_cents: cents,
        vat,
        total,
    };
}

function parseNote(value: unknown): DiscountNote | null {
    return isRecord(value) &&
        typeof value['applies'] === 'boolean' &&
        typeof value['reason'] === 'string'
        ? { applies: value['applies'], reason: value['reason'] }
        : null;
}

function parseSchedule(value: unknown): { effective_on: string } | null {
    return isRecord(value) && typeof value['effective_on'] === 'string'
        ? { effective_on: value['effective_on'] }
        : null;
}

/** A part that may be null: null stays null, and anything else must parse, or it is undefined (not one). */
export function optional<T>(
    value: unknown,
    parse: (v: unknown) => T | null,
): T | null | undefined {
    return value === null ? null : (parse(value) ?? undefined);
}

/** The server's answer to a plan being typed, or null when it is not one. */
export function parseSubscriptionPreview(
    value: unknown,
): SubscriptionPreview | null {
    if (!isRecord(value)) {
        return null;
    }

    const quote = parseQuote(value['quote']);
    const current = optional(value['current'], parseQuote);
    const first = optional(value['first_period'], parseQuote);
    const note = optional(value['eligibility'], parseNote);
    const scheduled = optional(value['scheduled'], parseSchedule);

    return quote === null ||
        current === undefined ||
        first === undefined ||
        note === undefined ||
        scheduled === undefined
        ? null
        : { quote, current, first_period: first, eligibility: note, scheduled };
}

/** "De $2,490 a $3,990": what the company pays now against what the plan being typed would cost; null when it is the same or either needs a quotation. */
export function priceChange(preview: SubscriptionPreview): string | null {
    const from = preview.current?.subtotal;
    const to = preview.quote.subtotal;

    if (from === null || from === undefined || to === null || from === to) {
        return null;
    }

    return `De ${from} a ${to}`;
}
