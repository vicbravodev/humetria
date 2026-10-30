import type { LevelCounts, Participation, RiskLevelValue } from '@/types';

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
