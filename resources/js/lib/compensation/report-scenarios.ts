import { percentOf } from '@/lib/chart';
import { bpText, wholePesos } from '@/lib/compensation/money';
import type { ScenarioReport } from '@/types';

export type ScenarioRow = {
    key: string;
    name: string;
    raiseText: string;
    costText: string;
    /** 0 to 100 of the dearest row. */
    percent: number;
    growthText: string;
    perPersonText: string;
    current: boolean;
};

type Draft = Omit<ScenarioRow, 'percent'> & { cents: number };

function withBars(rows: Draft[]): ScenarioRow[] {
    const dearest = Math.max(0, ...rows.map((row) => row.cents));

    return rows.map(({ cents, ...row }) => ({
        ...row,
        percent: percentOf(cents, dearest),
    }));
}

const growth = (bp: number): string => `+${bpText(bp)} %`;

/** «El mismo aumento a otros niveles»: the raise two points apart, each as it costs; none for a fixed amount. */
export function ladderRows(report: ScenarioReport): ScenarioRow[] {
    return withBars(
        report.ladder.map((rung) => ({
            key: rung.raise_text,
            name: rung.current ? `${rung.raise_text} (esta)` : rung.raise_text,
            raiseText: rung.raise_text,
            cents: rung.first_year_cents,
            costText: wholePesos(rung.first_year_cents),
            growthText: growth(rung.payroll_growth_bp),
            perPersonText: wholePesos(rung.per_person_month_cents),
            current: rung.current,
        })),
    );
}

/** «Tus otras propuestas»: this one and the other active scenarios of the account, the cheapest first. */
export function otherRows(report: ScenarioReport): ScenarioRow[] {
    const { headline, impact, scenario } = report;
    const rows: Draft[] = [
        {
            key: scenario.id,
            name: `${scenario.name} (esta)`,
            raiseText: scenario.raise_text,
            cents: headline.first_year_cents,
            costText: wholePesos(headline.first_year_cents),
            growthText: growth(impact.payroll_growth_bp),
            perPersonText: wholePesos(impact.per_person_month_cents),
            current: true,
        },
        ...report.others.map((other) => ({
            key: other.id,
            name: other.name,
            raiseText: other.raise_text,
            cents: other.first_year_cents,
            costText: wholePesos(other.first_year_cents),
            growthText: growth(other.payroll_growth_bp),
            perPersonText: wholePesos(other.per_person_month_cents),
            current: false,
        })),
    ];

    return withBars(rows.sort((a, b) => a.cents - b.cents));
}

/** What a point of raise costs, from the engine: the sentence under the scenarios. */
export function pointNote(report: ScenarioReport): string {
    return `Cada punto de aumento al salario cuesta ${report.sensitivity.one_point_text.replace(/\.\d{2}$/, '')} en el primer año, con las cuotas incluidas.`;
}
