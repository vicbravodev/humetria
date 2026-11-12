import type {
    BadgeVariant,
    OpenStatement,
    QuoteLine,
    StatementPreview,
    StatementState,
} from '@/types';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
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

/** The server's answer to a month being typed, or null when it is not one. */
export function parseStatementPreview(value: unknown): StatementPreview | null {
    if (!isRecord(value) || !Array.isArray(value['lines'])) {
        return null;
    }

    const lines = value['lines'].map(parseLine);
    const { period, subtotal, vat, total } = value;

    return lines.every((line) => line !== null) &&
        typeof period === 'string' &&
        typeof subtotal === 'string' &&
        typeof vat === 'string' &&
        typeof total === 'string'
        ? { period, lines, subtotal, vat, total }
        : null;
}

function parseOpenStatement(value: unknown): OpenStatement | null {
    if (!isRecord(value)) {
        return null;
    }

    const { id, label, balance, balance_input: input } = value;

    return typeof id === 'string' &&
        typeof label === 'string' &&
        typeof balance === 'string' &&
        typeof input === 'string'
        ? { id, label, balance, balance_input: input }
        : null;
}

/** The statements a payment can be applied to, or null when the answer is not a list of them. */
export function parseOpenStatements(value: unknown): OpenStatement[] | null {
    if (!isRecord(value) || !Array.isArray(value['data'])) {
        return null;
    }

    const rows = value['data'].map(parseOpenStatement);

    return rows.every((row) => row !== null) ? rows : null;
}

const VARIANTS: Record<StatementState, BadgeVariant> = {
    draft: 'outline',
    open: 'secondary',
    partially_paid: 'default',
    paid: 'default',
    credit: 'outline',
};

export function statementStateVariant(state: StatementState): BadgeVariant {
    return VARIANTS[state];
}

/** The month the generate form starts on: this one, as "2026-11", from today's date as "2026-11-10". */
export function defaultPeriod(today: string): string {
    return today.slice(0, 7);
}
