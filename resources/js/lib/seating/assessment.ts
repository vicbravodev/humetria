/** The conditions a person answers in their portal; the other five come from the position. */
const PERSONAL = ['footwear', 'discomfort'];

/**
 * The points chosen so far of the seven conditions (board LS-Evaluacion), out of their 7 to 21. Only a sum to guide the
 * Comisión: the total and the level are computed on the server when it signs, with the DOF's tables.
 */
export function scoreSummary(
    scores: Partial<Record<string, number>>,
    keys: string[],
): { total: number; answered: number; min: number; max: number } {
    const chosen = keys
        .map((key) => scores[key])
        .filter((points): points is number => points !== undefined);

    return {
        total: chosen.reduce((sum, points) => sum + points, 0),
        answered: chosen.length,
        min: keys.length,
        max: keys.length * 3,
    };
}

/**
 * Where a condition comes from: the position, or what the person answered. Discomfort without an answer is flagged: it is
 * worth 1 until the Comisión corrects it.
 */
export function conditionSource(
    key: string,
    personAnswered: boolean,
): { label: string; unanswered: boolean } {
    if (!PERSONAL.includes(key)) {
        return { label: 'Del puesto', unanswered: false };
    }

    return personAnswered
        ? { label: 'Contestó la persona', unanswered: false }
        : {
              label: 'Sin respuesta de la persona',
              unanswered: key === 'discomfort',
          };
}

/** Who starts an assessment when the person looking cannot: the Comisión, so the empty action says it instead of nothing. */
export function whoAssesses(
    state: 'none' | 'draft' | 'due' | 'signed',
    canAssess: boolean,
): string | null {
    return !canAssess && (state === 'none' || state === 'due')
        ? 'La hace la Comisión'
        : null;
}
