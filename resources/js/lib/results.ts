import type {
    ItemResult,
    LevelCounts,
    Participation,
    RiskGroup,
    RiskLevelValue,
} from '@/types';

/** Levels in the order counts come in: Nulo, Bajo, Medio, Alto, Muy alto. */
export const RISK_LEVELS: RiskLevelValue[] = [
    'null',
    'low',
    'medium',
    'high',
    'very_high',
];

/** The share of a count, rounded to a whole percent: "45 %" (es-MX writes a space before it). */
export function percentText(count: number, total: number): string {
    return `${total === 0 ? 0 : Math.round((count / total) * 100)} %`;
}

/** How many answered, of how many were invited: "11 de 12 (92 %)". */
export function participationText(participation: Participation): string {
    return `${participation.answered} de ${participation.invited} (${percentText(participation.answered, participation.invited)})`;
}

/** How many questionnaires fall from Medio up: those the norm asks to act on (C-14, Tablas 4 y 7). */
export function needingAction(counts: LevelCounts): number {
    return counts.slice(2).reduce((sum, count) => sum + count, 0);
}

/** How many filled segments the meter of a level shows: one for Nulo up to five for Muy alto. */
export function levelSegments(level: RiskLevelValue): number {
    return RISK_LEVELS.indexOf(level) + 1;
}

/** "1 área no se muestra" or "3 áreas no se muestran", or nothing when none is hidden. */
export function hiddenAreasText(hidden: number): string | null {
    if (hidden === 0) {
        return null;
    }

    return hidden === 1
        ? '1 área no se muestra'
        : `${hidden} áreas no se muestran`;
}

/** The share of a count as a number, unrounded: the width of a bar segment. */
export function shareOf(count: number, total: number): number {
    return total === 0 ? 0 : (count / total) * 100;
}

/** How many questionnaires fall in Alto or Muy alto: those the norm asks to analyze group by group (Tablas 4 y 7). */
export function highRisk(counts: LevelCounts): number {
    return counts.slice(3).reduce((sum, count) => sum + count, 0);
}

function total(counts: LevelCounts): number {
    return counts.reduce((sum, count) => sum + count, 0);
}

/** Groups from the worst off: by their share in Alto or Muy alto, then from Medio up; ties keep their order. */
export function rankBySeverity<T extends { levels: LevelCounts }>(
    rows: T[],
): T[] {
    const severity = (row: T): [number, number] => [
        shareOf(highRisk(row.levels), total(row.levels)),
        shareOf(needingAction(row.levels), total(row.levels)),
    ];

    return rows.toSorted((a, b) => {
        const [highA, actionA] = severity(a);
        const [highB, actionB] = severity(b);

        return highB - highA || actionB - actionA;
    });
}

/** One sentence on the final score of a group: how many are in Alto or Muy alto, or how far they are from it. */
export function riskVerdict(counts: LevelCounts): string {
    const answered = total(counts);
    const high = highRisk(counts);
    const medium = counts[2] ?? 0;

    if (high > 0) {
        return `${high} de ${answered} cuestionarios (${percentText(high, answered)}) quedan en riesgo alto o muy alto.`;
    }

    if (medium > 0) {
        return `Ningún cuestionario queda en riesgo alto o muy alto; ${medium} de ${answered} (${percentText(medium, answered)}) quedan en riesgo medio.`;
    }

    return `Los ${answered} cuestionarios quedan en riesgo nulo o bajo: la norma no pide un Programa de intervención.`;
}

/** Which group (a domain, a category) has the largest share in Alto or Muy alto; nothing when none has any. */
export function worstGroupText(
    groups: Pick<RiskGroup, 'label' | 'levels'>[],
    noun: string,
): string | null {
    const worst = rankBySeverity(groups)[0];

    if (worst === undefined || highRisk(worst.levels) === 0) {
        return null;
    }

    return `El ${noun} que más pesa es ${worst.label}: ${percentText(highRisk(worst.levels), total(worst.levels))} en riesgo alto o muy alto.`;
}

/** "7 de 29 desfavorables (24 %)": the answers to a question on the side that scores risk. */
export function unfavorableText(unfavorable: number, answered: number): string {
    return `${unfavorable} de ${answered} desfavorables (${percentText(unfavorable, answered)})`;
}

/** Questions from the most unfavorably answered; those not shown (under the minimum) last, in their order. */
export function rankItems(items: ItemResult[]): ItemResult[] {
    const share = (item: ItemResult): number =>
        item.unfavorable === null
            ? -1
            : shareOf(item.unfavorable, item.answered);

    return items.toSorted((a, b) => share(b) - share(a));
}
