import { percentOf } from '@/lib/chart';
import { percentText, wholePesos } from '@/lib/compensation/money';
import { costSteps } from '@/lib/compensation/report-charts';
import type { CostStep } from '@/lib/compensation/report-charts';
import type { ScenarioReport } from '@/types';

export type CostGroup = {
    key: string;
    label: string;
    valueText: string;
    shareText: string;
    /** 0 to 100 of the first year. */
    percent: number;
    note: string;
};

const BONUSES = ['benefit:christmas_bonus', 'benefit:vacation_premium'];

const NOTES: Record<string, string> = {
    salary: 'El aumento mismo.',
    bonuses: 'Suben porque se pagan sobre el salario diario.',
    imss: 'Suben con el nuevo SBC; la cuota fija no.',
    'contribution:infonavit': 'Se calcula sobre el nuevo SBC.',
    'contribution:payroll_tax': 'Se calcula sobre la nómina gravable.',
};

/** The aguinaldo and the premium are one line for a reader: what is paid on the salary of the days. */
function merged(steps: CostStep[]): CostStep[] {
    const bonuses = steps.filter((step) => BONUSES.includes(step.key));
    const cents = bonuses.reduce((sum, step) => sum + step.cents, 0);
    const rest = steps.filter((step) => !BONUSES.includes(step.key));

    return bonuses.length === 0
        ? rest
        : [
              ...rest,
              { key: 'bonuses', label: 'Aguinaldo y prima vacacional', cents },
          ].sort(
              (a, b) =>
                  Number(b.key === 'salary') - Number(a.key === 'salary') ||
                  b.cents - a.cents,
          );
}

/** «Qué sube y por qué»: each source of the cost with its share of the first year and the reason it moves. */
export function costGroups(report: ScenarioReport): CostGroup[] {
    const total = report.headline.first_year_cents;

    return merged(costSteps(report.concepts)).map((step) => ({
        key: step.key,
        label: step.label,
        valueText: wholePesos(step.cents),
        shareText: percentText(step.cents, total),
        percent: percentOf(step.cents, total),
        note:
            NOTES[step.key] ??
            'Prestación del contrato que sube con el salario.',
    }));
}

/**
 * What does not move with the raise: the fixed quota of the IMSS depends on the UMA and not on the salary, and the report says
 * so with what it costs a year; nothing when every line moves.
 */
export function unchangedNote(report: ScenarioReport): string | null {
    const fixed = report.concepts.find(
        (concept) =>
            concept.key === 'imss_fixed' &&
            concept.first_year_cents === 0 &&
            concept.after_text !== '$0.00',
    );

    return fixed === undefined
        ? null
        : `No sube con el aumento: la cuota fija del IMSS, ${fixed.after_text.replace(/\.00$/, '')} al año, porque se calcula con la UMA y no con el salario.`;
}
