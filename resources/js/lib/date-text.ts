/*
 * What a person types or pastes in a date or month field, read the way Mexico writes it: day first ("07/10/2018",
 * "7-10-18", "7 oct 2018", "7 de octubre de 2018") or as the server writes it ("2018-10-07"). It always comes out as
 * Y-m-d (or Y-m for a month), the only shape the server takes.
 */

/** What a typed text is: nothing, not a date, before the first day allowed, after the last one, or a day. */
export type TextReading =
    | { kind: 'empty' }
    | { kind: 'invalid' }
    | { kind: 'early' }
    | { kind: 'late' }
    | { kind: 'ok'; value: string };

/** The first and last value allowed, both included, in the server's shape (Y-m-d or Y-m). */
export type TextLimits = { min?: string | undefined; max?: string | undefined };

const months = [
    ['ene', 'enero'],
    ['feb', 'febrero'],
    ['mar', 'marzo'],
    ['abr', 'abril'],
    ['may', 'mayo'],
    ['jun', 'junio'],
    ['jul', 'julio'],
    ['ago', 'agosto'],
    ['sep', 'sept', 'septiembre', 'setiembre'],
    ['oct', 'octubre'],
    ['nov', 'noviembre'],
    ['dic', 'diciembre'],
];

const pad = (part: number): string => String(part).padStart(2, '0');

/** Lowercase, without accents, and with every run of spaces as one. */
function tidy(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

function monthNamed(word: string): number | undefined {
    const index = months.findIndex((names) =>
        names.includes(word.replace(/\.$/, '')),
    );

    return index === -1 ? undefined : index + 1;
}

/** A two-digit year falls in this century up to twenty years ahead of today, and in the last one after that. */
function fullYear(text: string, today: Date): number | undefined {
    if (text.length === 4) {
        return Number(text);
    }

    if (text.length !== 2) {
        return undefined;
    }

    const year = 2000 + Number(text);

    return year <= today.getFullYear() + 20 ? year : year - 100;
}

function daysIn(year: number, month: number): number {
    return new Date(year, month, 0).getDate();
}

function reading(
    value: string | undefined,
    limits: TextLimits,
    shape: RegExp,
): TextReading {
    if (value === undefined) {
        return { kind: 'invalid' };
    }

    // Both ends are written the same zero-padded way, so they compare as text.
    if (
        limits.min !== undefined &&
        shape.test(limits.min) &&
        value < limits.min
    ) {
        return { kind: 'early' };
    }

    if (
        limits.max !== undefined &&
        shape.test(limits.max) &&
        value > limits.max
    ) {
        return { kind: 'late' };
    }

    return { kind: 'ok', value };
}

function dayOf(
    year: number | undefined,
    month: number | undefined,
    day: number,
): string | undefined {
    if (
        year === undefined ||
        month === undefined ||
        year < 1900 ||
        year > 2999
    ) {
        return undefined;
    }

    if (month < 1 || month > 12 || day < 1 || day > daysIn(year, month)) {
        return undefined;
    }

    return `${year}-${pad(month)}-${pad(day)}`;
}

function readDay(text: string, today: Date): string | undefined {
    const server = /^(\d{4}) ?[-/.] ?(\d{1,2}) ?[-/.] ?(\d{1,2})$/.exec(text);

    if (server !== null) {
        return dayOf(Number(server[1]), Number(server[2]), Number(server[3]));
    }

    const numbers =
        /^(\d{1,2}) ?[-/. ] ?(\d{1,2}) ?[-/. ] ?(\d{2}|\d{4})$/.exec(text) ??
        /^(\d{2})(\d{2})(\d{4})$/.exec(text);

    if (numbers !== null) {
        return dayOf(
            fullYear(numbers[3] ?? '', today),
            Number(numbers[2]),
            Number(numbers[1]),
        );
    }

    const words =
        /^(\d{1,2}) (?:de )?([a-z]+\.?) (?:de |del )?(\d{2}|\d{4})$/.exec(text);

    return words === null
        ? undefined
        : dayOf(
              fullYear(words[3] ?? '', today),
              monthNamed(words[2] ?? ''),
              Number(words[1]),
          );
}

/** Reads a typed or pasted date as Y-m-d, kept between `min` and `max` when they are dates. */
export function parseDateText(
    text: string,
    limits: TextLimits = {},
    today: Date = new Date(),
): TextReading {
    const tidied = tidy(text);

    return tidied === ''
        ? { kind: 'empty' }
        : reading(readDay(tidied, today), limits, /^\d{4}-\d{2}-\d{2}$/);
}

/** "07/10/2018" for 2018-10-07; nothing for what is not a day. */
export function formatDateText(value: string): string {
    const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (
        parts === null ||
        dayOf(Number(parts[1]), Number(parts[2]), Number(parts[3])) ===
            undefined
    ) {
        return '';
    }

    return `${parts[3]}/${parts[2]}/${parts[1]}`;
}

/**
 * Writes eight digits, typed or pasted, as a date ("07102018" → "07/10/2018"). Nothing is added while typing: the
 * first digits may still be the year of a 2018-10-07.
 */
export function maskDateText(text: string): string {
    if (/^\d{8}$/.test(text)) {
        return `${text.slice(0, 2)}/${text.slice(2, 4)}/${text.slice(4)}`;
    }

    return text;
}

function monthOf(
    year: number | undefined,
    month: number | undefined,
): string | undefined {
    if (
        year === undefined ||
        month === undefined ||
        year < 1900 ||
        year > 2999 ||
        month < 1 ||
        month > 12
    ) {
        return undefined;
    }

    return `${year}-${pad(month)}`;
}

function readMonth(text: string, today: Date): string | undefined {
    const server = /^(\d{4}) ?[-/.] ?(\d{1,2})$/.exec(text);

    if (server !== null) {
        return monthOf(Number(server[1]), Number(server[2]));
    }

    const numbers =
        /^(\d{1,2}) ?[-/. ] ?(\d{2}|\d{4})$/.exec(text) ??
        /^(\d{2})(\d{4})$/.exec(text);

    if (numbers !== null) {
        return monthOf(fullYear(numbers[2] ?? '', today), Number(numbers[1]));
    }

    const words = /^([a-z]+\.?) (?:de |del )?(\d{2}|\d{4})$/.exec(text);

    return words === null
        ? undefined
        : monthOf(fullYear(words[2] ?? '', today), monthNamed(words[1] ?? ''));
}

/** Reads a typed or pasted month as Y-m, kept between `min` and `max` when they are months. */
export function parseMonthText(
    text: string,
    limits: TextLimits = {},
    today: Date = new Date(),
): TextReading {
    const tidied = tidy(text);

    return tidied === ''
        ? { kind: 'empty' }
        : reading(readMonth(tidied, today), limits, /^\d{4}-\d{2}$/);
}

/** "10/2018" for 2018-10; nothing for what is not a month. */
export function formatMonthText(value: string): string {
    const parts = /^(\d{4})-(\d{2})$/.exec(value);

    return parts === null ||
        monthOf(Number(parts[1]), Number(parts[2])) === undefined
        ? ''
        : `${parts[2]}/${parts[1]}`;
}

/** Writes six digits, typed or pasted, as a month ("102018" → "10/2018"). */
export function maskMonthText(text: string): string {
    if (/^\d{6}$/.test(text)) {
        return `${text.slice(0, 2)}/${text.slice(2)}`;
    }

    return text;
}
