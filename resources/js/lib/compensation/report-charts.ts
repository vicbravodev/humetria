import { cascade, niceCeiling, percentOf } from '@/lib/chart';
import { amountText, percentText, wholePesos } from '@/lib/compensation/money';
import { compareBars } from '@/lib/compensation/scenario-draft';
import type { CompareBar } from '@/lib/compensation/scenario-draft';
import { countOf } from '@/lib/plural';
import type { ReportConcept, ReportRaise, ScenarioReport } from '@/types';

/** The employer lines that are the quotas of the IMSS for a reader: its branches, retirement and cesantía. */
const IMSS_LINES = ['work_risk', 'retirement', 'severance_old_age'];

export type CostStep = { key: string; label: string; cents: number };

/** What each concept adds to the first year as the cascade draws it: the quotas of the IMSS together, from the salary down. */
export function costSteps(concepts: ReportConcept[]): CostStep[] {
    const steps = new Map<string, CostStep>();

    for (const concept of concepts) {
        const imss =
            concept.type === 'contribution' &&
            (concept.key.startsWith('imss_') ||
                IMSS_LINES.includes(concept.key));
        const key =
            concept.type === 'salary'
                ? 'salary'
                : imss
                  ? 'imss'
                  : `${concept.type}:${concept.key}`;
        const cents = (steps.get(key)?.cents ?? 0) + concept.first_year_cents;
        steps.set(key, {
            key,
            label: imss ? 'Cuotas del IMSS' : concept.label,
            cents,
        });
    }

    return [...steps.values()]
        .filter((step) => step.cents !== 0)
        .sort(
            (a, b) =>
                Number(b.key === 'salary') - Number(a.key === 'salary') ||
                b.cents - a.cents,
        );
}

export type WaterfallBar = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    from: number;
    to: number;
    total: boolean;
};

/** «De dónde sale el costo»: each concept stacked to the first year, with its share in the tooltip. */
export function waterfallBars(report: ScenarioReport): WaterfallBar[] {
    const steps = costSteps(report.concepts);
    const total = report.headline.first_year_cents;
    const bars = cascade(steps.map((step) => step.cents));
    const last = bars.at(-1) ?? { from: 0, to: 0 };

    return [
        ...steps.map((step, index) => ({
            key: step.key,
            label: step.label,
            valueText: wholePesos(step.cents),
            tooltip: `${step.label}: ${wholePesos(step.cents)} · ${percentText(step.cents, total)} del primer año`,
            from: bars[index]?.from ?? 0,
            to: bars[index]?.to ?? 0,
            total: false,
        })),
        {
            key: 'total',
            label: 'Primer año',
            valueText: wholePesos(total),
            tooltip: `Total del primer año: ${wholePesos(total)}`,
            ...last,
            total: true,
        },
    ];
}

export type RaiseRow = {
    key: string;
    label: string;
    people: string;
    valueText: string;
    tooltip: string;
    percent: number;
};

export type RaiseGroup = {
    key: ReportRaise['group'];
    title: string;
    people: string;
    rows: RaiseRow[];
};

function peopleText(people: number, union: boolean): string {
    return countOf(
        people,
        union ? 'agremiado' : 'persona',
        union ? 'agremiados' : 'personas',
    );
}

/** «Quién recibe el aumento»: pesos a month by category, sindicalizados and confianza on one scale of hundreds of pesos. */
export function raiseGroups(
    report: ScenarioReport,
    union: boolean,
): { groups: RaiseGroup[]; scaleText: string } {
    const { scenario } = report;
    const amount = scenario.raise_unit === 'amount';
    const scale = niceCeiling(
        Math.max(
            0,
            ...report.raises.map((raise) => raise.monthly_raise_cents),
        ) / 100,
        100,
    );
    const groups = (
        [
            ['unionized', 'Sindicalizados', scenario.unionized_raise],
            [
                'trusted',
                'Confianza',
                scenario.trusted_raise ?? scenario.unionized_raise,
            ],
        ] satisfies [ReportRaise['group'], string, string][]
    ).map(([key, name, raise]) => {
        const raises = report.raises.filter((row) => row.group === key);
        const piece = amount ? `${amountText(raise)} al mes` : `${raise} %`;

        return {
            key,
            title: `${name} · ${piece}`,
            people: peopleText(
                raises.reduce((sum, row) => sum + row.people, 0),
                union,
            ),
            rows: raises.map((row) => ({
                key: row.label,
                label: row.label,
                people: peopleText(row.people, union),
                valueText: `+${wholePesos(row.monthly_raise_cents)}`,
                tooltip: `${row.label}: +${wholePesos(row.monthly_raise_cents)} al mes (${amount ? 'monto fijo' : piece})`,
                percent: percentOf(row.monthly_raise_cents / 100, scale),
            })),
        };
    });

    return {
        groups: groups.filter((group) => group.rows.length > 0),
        scaleText: `Escala común de $0 a ${wholePesos(scale * 100)} al mes. Sueldo mensual = salario diario × 30.4.`,
    };
}

/** The same salary of a group spread evenly: whole pesos a month a person; none for a group with nobody. */
function evenPesos(raises: ReportRaise[]): number | null {
    const people = raises.reduce((sum, row) => sum + row.people, 0);
    const cents = raises.reduce(
        (sum, row) => sum + row.monthly_raise_cents * row.people,
        0,
    );

    return people === 0 ? null : Math.round(cents / people / 100);
}

export type FixedAlternative = {
    amountText: string;
    lowest: string;
    moreText: string;
    query: {
        name: string;
        raise_unit: 'amount';
        unionized_raise: string;
        trusted_raise: string;
        retroactive_months: number;
    };
};

/**
 * «Un monto fijo reparte distinto»: the fixed amount a month that pays the sindicalizados the same in salary as the percent,
 * and what the lowest paid category would receive more with it. Nothing for a fixed amount already, one category alone or a
 * raise that is already even.
 */
export function fixedAlternative(
    report: ScenarioReport,
): FixedAlternative | null {
    const unionized = report.raises.filter((row) => row.group === 'unionized');
    const even = evenPesos(unionized);
    const lowest = [...unionized].sort(
        (a, b) => a.monthly_raise_cents - b.monthly_raise_cents,
    )[0];

    if (
        report.scenario.raise_unit === 'amount' ||
        unionized.length < 2 ||
        even === null ||
        lowest === undefined ||
        even * 100 <= lowest.monthly_raise_cents
    ) {
        return null;
    }

    const trusted = evenPesos(
        report.raises.filter((row) => row.group === 'trusted'),
    );

    return {
        amountText: wholePesos(even * 100),
        lowest: lowest.label,
        moreText: wholePesos(even * 100 - lowest.monthly_raise_cents),
        query: {
            name: `${report.scenario.name} · monto fijo`,
            raise_unit: 'amount',
            unionized_raise: String(even),
            trusted_raise: trusted === null ? '' : String(trusted),
            retroactive_months: report.scenario.retroactive_months,
        },
    };
}

export type YearColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    percent: number;
};

/** «Los próximos años»: what the proposal adds each year against the dearest year, and all of them together. */
export function yearColumns(report: ScenarioReport): {
    columns: YearColumn[];
    totalText: string;
} {
    const dearest = Math.max(
        0,
        ...report.projection.map((year) => year.cost_cents),
    );

    return {
        columns: report.projection.map((year) => {
            const label = year.effective_on.slice(0, 4);
            const value = wholePesos(year.cost_cents);

            return {
                key: String(year.year),
                label,
                valueText: value,
                tooltip: `${label}: ${value}${year.retroactive_text === null ? '' : ' · incluye el retroactivo'}`,
                percent: percentOf(year.cost_cents, dearest),
            };
        }),
        totalText: wholePesos(
            report.projection.reduce((sum, year) => sum + year.cost_cents, 0),
        ),
    };
}

/** «Frente a las otras propuestas»: this one and the others of the account, from the cheapest; nothing without others. */
export function otherBars(report: ScenarioReport): CompareBar[] {
    return compareBars(report.others, {
        cents: report.headline.first_year_cents,
        text: wholePesos(report.headline.first_year_cents),
    }).map((bar) =>
        bar.current ? { ...bar, name: `${report.scenario.name} (esta)` } : bar,
    );
}

/** The scenarios «Ver la comparación completa» opens: this one and the first three others, as many as it compares. */
export function compareIds(report: ScenarioReport): string[] {
    return [
        report.scenario.id,
        ...report.others.slice(0, 3).map((other) => other.id),
    ];
}
