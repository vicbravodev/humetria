/**
 * The minimum of people per group of a result (criterio C-23): the one a campaign froze when it launched, or the
 * company's for what is still to launch. With 1 the company set none: nothing is hidden, and no text may promise it.
 */

/** What RH is told instead of a promise of hiding, when there is no minimum. */
export const NO_GROUP_MINIMUM =
    'Tu empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar respuestas.';

/** Whether the minimum hides small groups: from 2 on; with 1 there is no minimum. */
export function hidesSmallGroups(minimum: number): boolean {
    return minimum > 1;
}

/** Why a group shows nothing, in a few words: fewer `noun` than the minimum, or (with no minimum) none at all. */
export function tooFewText(minimum: number, noun: string): string {
    return hidesSmallGroups(minimum)
        ? `Menos de ${minimum} ${noun}`
        : `Sin ${noun}`;
}

/** Why a closed evaluation shows no results: `whenHidden` under a minimum; with none, that nothing was answered. */
export function withheldText(minimum: number, whenHidden: string): string {
    return hidesSmallGroups(minimum)
        ? whenHidden
        : 'No se recibieron cuestionarios, así que no hay resultados que mostrar.';
}

/** Which groups show their results, in a sentence; with no minimum, the warning instead. */
export function groupsShownText(minimum: number): string {
    return hidesSmallGroups(minimum)
        ? `Los resultados solo se muestran con grupos de ${minimum} o más personas.`
        : NO_GROUP_MINIMUM;
}

/** The fact under a page title: the minimum of a group, or that there is none. */
export function groupsFactText(minimum: number): string {
    return hidesSmallGroups(minimum)
        ? `Grupos de ${minimum} o más`
        : 'Sin mínimo por grupo';
}

/** What whoever answers is told (in usted, as `group-minimum.notice`): the minimum, or with none the warning. */
export function groupNoticeText(minimum: number): string {
    return hidesSmallGroups(minimum)
        ? `Recursos Humanos solo recibe resultados de grupos de ${minimum} o más personas.`
        : 'Su empresa no fijó un mínimo de personas por grupo: el resultado de un grupo muy pequeño podría permitir identificar sus respuestas.';
}

/** What the owner is told while choosing a minimum under the recommended one; null at it or above. */
export function lowMinimumWarning(
    minimum: number,
    recommended: number,
): string | null {
    if (minimum >= recommended) {
        return null;
    }

    return hidesSmallGroups(minimum)
        ? `Con grupos de ${minimum} personas es más fácil adivinar quién respondió qué. Recomendamos ${recommended} o más.`
        : 'Sin mínimo, el resultado de un grupo de una persona mostrará su respuesta, y el de un grupo muy pequeño podría permitir identificar respuestas.';
}

/** The minimum one person up or down, kept from `lowest` to `highest`; what is not a number starts at `lowest`. */
export function stepMinimum(
    value: string,
    direction: 1 | -1,
    { lowest, highest }: { lowest: number; highest: number },
): string {
    const current = Math.trunc(Number(value));
    const next =
        Number.isFinite(current) && value.trim() !== ''
            ? current + direction
            : lowest;

    return String(Math.min(highest, Math.max(lowest, next)));
}
