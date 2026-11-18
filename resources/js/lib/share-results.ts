import { hidesSmallGroups, NO_GROUP_MINIMUM } from '@/lib/group-minimum';

/**
 * Who answered, as each person reads it (board V2-N035-Compartir): below the minimum, never the count (criterio C-54). With no
 * minimum (1) only nobody is below it.
 */
export function answeredLine(
    answered: number | null,
    minimum: number,
    center: string,
): string {
    if (answered === null) {
        return hidesSmallGroups(minimum)
            ? `Contestaron menos de ${minimum} personas de ${center}: con tan pocas no se muestran cifras.`
            : `Nadie de ${center} contestó: no hay cifras que mostrar.`;
    }

    return `Contestaron ${answered === 1 ? '1 persona' : `${answered} personas`} de ${center}. Gracias por su tiempo.`;
}

/** The themes that most need attention in one sentence: «violencia, carga de trabajo y jornada de trabajo». */
export function themesLine(themes: string[]): string | null {
    if (themes.length === 0) {
        return null;
    }

    const lower = themes.map((theme, index) =>
        index === 0 ? theme : theme.toLowerCase(),
    );
    const last = lower.at(-1) ?? '';

    return lower.length === 1
        ? `${last}.`
        : `${lower.slice(0, -1).join(', ')} y ${last}.`;
}

/** How many answered, in a few words for RH: the count, «Menos de N» below the minimum, or that nobody did with none. */
export function answeredDetail(
    answered: number | null,
    minimum: number,
): string {
    if (answered !== null) {
        return `${answered}`;
    }

    return hidesSmallGroups(minimum) ? `Menos de ${minimum}` : 'Nadie contestó';
}

/** What the people will see of an evaluation's results, with the minimum it froze, or the warning when it has none. */
export function peopleResultsText(minimum: number): string {
    const seen =
        'Verán cuántas personas contestaron y los temas que más atención necesitan';

    return hidesSmallGroups(minimum)
        ? `${seen}. Nunca datos de áreas o grupos con menos de ${minimum} personas.`
        : `${seen}, del centro completo y nunca por área. ${NO_GROUP_MINIMUM}`;
}
