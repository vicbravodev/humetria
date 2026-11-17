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

/**
 * The step to mark for one tapped: itself when it proceeds, or else the first one the server offers, so no signature is
 * spent on a step that would be refused; the one tapped when the server offered none.
 */
export function stepThatProceeds(
    tapped: MarkType,
    next: readonly string[],
): MarkType {
    return next.includes(tapped) ? tapped : (next.find(isMarkType) ?? tapped);
}

/** What goes first, when the step tapped does not proceed: «Primero marque su entrada.» */
export function firstStepNotice(next: readonly string[]): string {
    const first = next.find(isMarkType);

    return first === undefined
        ? 'Ese paso no corresponde ahora.'
        : `Primero marque su ${NAMES[first].toLowerCase()}.`;
}
