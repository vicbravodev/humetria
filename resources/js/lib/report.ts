import { highRisk, shareOf } from '@/lib/results';
import type { AreaResults, LevelCounts, RiskGroup } from '@/types';

/** What each domain of Guías II and III looks at, in a line, for whoever reads the report. */
const DOMAIN_DESCRIPTIONS: Record<string, string> = {
    environment_conditions:
        'Condiciones peligrosas, deficientes o insalubres del lugar de trabajo.',
    workload:
        'Cantidad y ritmo del trabajo, y su exigencia mental, emocional y de responsabilidad.',
    lack_of_control:
        'Qué tanto decide cada persona sobre su trabajo, y si puede capacitarse y crecer.',
    work_day: 'Jornadas extensas: horas extra y trabajo en días de descanso.',
    work_family: 'Qué tanto invade el trabajo el tiempo personal y familiar.',
    leadership:
        'Cómo dirige el jefe inmediato: claridad de funciones, apoyo y trato.',
    relations:
        'El trato y la colaboración entre compañeros, y con quienes se supervisa.',
    violence: 'Hostigamiento, acoso o malos tratos que se viven en el trabajo.',
    performance_recognition:
        'Si se reconoce el trabajo y su resultado, y si hay retroalimentación.',
    belonging_instability:
        'El compromiso con el centro de trabajo y la estabilidad del empleo.',
};

const SIDE_SCALE = 80;
const FOCUS_COUNT = 3;

export function total(counts: LevelCounts): number {
    return counts.reduce((sum, count) => sum + count, 0);
}

function highShare(counts: LevelCounts): number {
    return shareOf(highRisk(counts), total(counts));
}

/** Groups from the largest share in Alto or Muy alto; ties by how many fall in Muy alto, then in their order. */
export function rankByHighRisk<T extends { levels: LevelCounts }>(
    rows: T[],
): T[] {
    return rows.toSorted(
        (a, b) =>
            highShare(b.levels) - highShare(a.levels) ||
            (b.levels[4] ?? 0) - (a.levels[4] ?? 0),
    );
}

/** Where to act first: up to three domains with someone in Alto or Muy alto, the worst first. */
export function focusDomains<T extends { levels: LevelCounts }>(
    domains: T[],
): T[] {
    return rankByHighRisk(domains)
        .filter((domain) => highRisk(domain.levels) > 0)
        .slice(0, FOCUS_COUNT);
}

/** The level (0 Nulo to 4 Muy alto) most questionnaires fall in; on a tie, the higher one. */
export function mostFrequentLevel(counts: LevelCounts): number {
    return counts.reduce(
        (best, count, index) => (count >= (counts[best] ?? 0) ? index : best),
        0,
    );
}

/** One square per questionnaire by its level (0 Nulo to 4 Muy alto), then an empty one (null) per person missing. */
export function waffleCells(
    final: LevelCounts,
    missing: number,
): (number | null)[] {
    return [
        ...final.flatMap((count, level) =>
            Array.from({ length: count }, () => level),
        ),
        ...Array.from({ length: Math.max(0, missing) }, () => null),
    ];
}

/** Ten squares per row up to fifty, twenty above: a sample of hundreds still fits on a page. */
export function waffleColumns(squares: number): number {
    return squares <= 50 ? 10 : 20;
}

/** A group split in Nulo to Medio (low) and in Alto or Muy alto (high), as shares of it: the two sides of the axis. */
export function sideShares(counts: LevelCounts): { low: number; high: number } {
    const answered = total(counts);
    const high = highRisk(counts);

    // Multiplied first, so whole shares stay whole (0.6 × 100 is not 60 in floating point).
    return answered === 0
        ? { low: 0, high: 0 }
        : {
              low: ((answered - high) * 100) / answered,
              high: (high * 100) / answered,
          };
}

/** How far each side of the axis reads: 0 to 80 %, or up to the next ten when a group goes past it. */
export function divergingScale(rows: { levels: LevelCounts }[]): number {
    const widest = Math.max(
        SIDE_SCALE,
        ...rows.flatMap((row) => Object.values(sideShares(row.levels))),
    );

    return Math.ceil(widest / 10) * 10;
}

/** Each category with its domains, both from the worst off. */
export function nestDomains(
    categories: RiskGroup[],
    domains: RiskGroup[],
): { category: RiskGroup; domains: RiskGroup[] }[] {
    return rankByHighRisk(categories).map((category) => ({
        category,
        domains: rankByHighRisk(
            domains.filter((domain) => domain.category === category.key),
        ),
    }));
}

/** An area's final score in three: Nulo or Bajo, Medio, and Alto or Muy alto. */
export function areaBuckets(final: LevelCounts): LevelCounts {
    return [(final[0] ?? 0) + (final[1] ?? 0), final[2] ?? 0, highRisk(final)];
}

/** How many questionnaires belong to the areas not shown: they count in the total, never on their own. */
export function hiddenQuestionnaires(
    answered: number,
    areas: AreaResults,
): number {
    return (
        answered - areas.visible.reduce((sum, area) => sum + area.answered, 0)
    );
}

export function domainDescription(key: string): string | null {
    return DOMAIN_DESCRIPTIONS[key] ?? null;
}

/** The tone a share in Alto or Muy alto is written in: Muy alto from 60 %, Alto from 45 %, plain below. */
export function highTone(percent: number): 'very_high' | 'high' | null {
    if (percent >= 60) {
        return 'very_high';
    }

    return percent >= 45 ? 'high' : null;
}

const SECTIONS = [
    { id: 'resumen', label: 'Resumen', results: true },
    { id: 'focos', label: 'Focos', results: true },
    { id: 'dominios', label: 'Dominios', results: true },
    { id: 'areas', label: 'Áreas', results: true },
    { id: 'informe', label: 'Informe 7.7', results: false },
    { id: 'anexo', label: 'Anexo', results: false },
];

/** The parts of the report to jump to; with no results to show, only the 7.7 incises and the annex. */
export function reportSections(
    visible: boolean,
): { id: string; label: string }[] {
    return SECTIONS.filter((section) => visible || !section.results).map(
        ({ id, label }) => ({ id, label }),
    );
}
