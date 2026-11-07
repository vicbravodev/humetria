/**
 * The buttons of Marcar. What may come next is the server's to say (MarkType::allowedAfter, sent as `next` with the page
 * and after every mark): here it is only named and ordered, so the sequence is written in one place.
 */

export type MarkType = 'start' | 'break_start' | 'break_end' | 'end';

export type Step = { type: MarkType; label: string };

const ACTIONS: Record<MarkType, string> = {
    start: 'Marcar entrada',
    break_start: 'Salir a comer',
    break_end: 'Regresar de comer',
    end: 'Marcar salida',
};

const NAMES: Record<MarkType, string> = {
    start: 'Entrada',
    break_start: 'Salida a comer',
    break_end: 'Regreso de comer',
    end: 'Salida',
};

const TYPES: readonly MarkType[] = ['start', 'break_start', 'break_end', 'end'];

/** Every step, for a person not known yet in this browser: the server tells which one follows. */
export const ANY_STEP: readonly string[] = TYPES;

export function isMarkType(value: unknown): value is MarkType {
    return TYPES.some((type) => type === value);
}

/** The big button (the first step the server offers) and the others, smaller. */
export function nextSteps(next: readonly string[]): {
    primary: Step | null;
    others: Step[];
} {
    const steps = next
        .filter(isMarkType)
        .map((type) => ({ type, label: ACTIONS[type] }));

    return { primary: steps[0] ?? null, others: steps.slice(1) };
}

/** A mark of the day, by name. */
export function markName(type: string): string {
    return isMarkType(type) ? NAMES[type] : 'Marca';
}
