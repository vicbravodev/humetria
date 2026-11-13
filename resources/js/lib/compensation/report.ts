import { wholePesos } from '@/lib/compensation/money';
import { costSteps } from '@/lib/compensation/report-charts';
import { retroText } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

const PESOS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});
const TENTHS = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 });
const MILLIONS = new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** How a figure of the summary is written as it counts up: pesos, or tenths of a percent with their sign. */
export type FigureUnit = 'pesos' | 'tenths';

export type SummaryFigure = {
    key: string;
    caption: string;
    /** What it counts up to: whole pesos, or tenths of a percent. */
    value: number;
    unit: FigureUnit;
    note: string;
};

export function figureText(unit: FigureUnit, value: number): string {
    if (unit === 'pesos') {
        return PESOS.format(value);
    }

    return `${value > 0 ? '+' : ''}${TENTHS.format(value / 10)} %`;
}

/** "$19.22 millones", or whole pesos under a million. */
function payrollNote(before: number, after: number): string {
    const millions = (cents: number): string =>
        `$${MILLIONS.format(cents / 100_000_000)}`;

    return Math.min(before, after) >= 100_000_000
        ? `De ${millions(before)} a ${millions(after)} millones al año`
        : `De ${wholePesos(before)} a ${wholePesos(after)} al año`;
}

function peopleText(people: number, union: boolean): string {
    return countOf(
        people,
        union ? 'agremiado' : 'persona',
        union ? 'agremiados' : 'personas',
    );
}

/** The four figures of the summary (board V4-Comp-Reporte): the first year, each year after, the payroll and a person a month. */
export function summaryFigures(
    report: ScenarioReport,
    union: boolean,
): SummaryFigure[] {
    const { headline } = report;
    const pesos = (cents: number): number => Math.round(cents / 100);
    const months = headline.retroactive_months;

    return [
        {
            key: 'first_year',
            caption: 'Costo del primer año',
            value: pesos(headline.first_year_cents),
            unit: 'pesos',
            note:
                months > 0
                    ? `Incluye ${wholePesos(headline.retroactive_cents)} de ${retroText(months)}`
                    : 'Sin retroactivo',
        },
        {
            key: 'recurring',
            caption: 'Al año, al arranque',
            value: pesos(headline.recurring_cents),
            unit: 'pesos',
            note: 'Los años siguientes cambian: mira la proyección',
        },
        {
            key: 'payroll',
            caption: 'Nómina con cargas',
            value:
                headline.before_cents === 0
                    ? 0
                    : Math.round(
                          (headline.recurring_cents / headline.before_cents) *
                              1000,
                      ),
            unit: 'tenths',
            note: payrollNote(headline.before_cents, headline.after_cents),
        },
        {
            key: 'per_person',
            caption: 'Por persona al mes',
            value:
                headline.people === 0
                    ? 0
                    : pesos(headline.recurring_cents / headline.people / 12),
            unit: 'pesos',
            note: `Costo promedio para la empresa, ${peopleText(headline.people, union)}`,
        },
    ];
}

export type Finding = { figure: string; title: string; detail: string };

/** "3 de 4" when the share is near a quarter, "9 de 10" otherwise. */
function shareFigure(share: number): string {
    const quarters = Math.round(share * 4);

    return quarters > 0 &&
        quarters < 4 &&
        Math.abs(share - quarters / 4) <= 0.03
        ? `${quarters} de 4`
        : `${Math.round(share * 10)} de 10`;
}

/** The names the charges are known by in a sentence: "IMSS", "Infonavit" and the rest in lower case. */
function chargeName(label: string): string {
    if (label === 'Cuotas del IMSS') {
        return 'IMSS';
    }

    return label === 'Infonavit'
        ? label
        : label.charAt(0).toLocaleLowerCase('es-MX') + label.slice(1);
}

function costFindings(report: ScenarioReport): Finding[] {
    const steps = costSteps(report.concepts);
    const salary = steps.find((step) => step.key === 'salary')?.cents ?? 0;
    const total = report.headline.first_year_cents;

    if (salary <= 0 || total <= 0) {
        return [];
    }

    const charges = steps.filter((step) => step.key !== 'salary');

    return [
        {
            figure: shareFigure(salary / total),
            title: 'pesos del costo van al salario.',
            detail: 'El resto son cuotas y prestaciones que suben solas con él.',
        },
        ...(charges.length === 0
            ? []
            : [
                  {
                      figure: `${Math.round(((total - salary) / salary) * 100)} ¢`,
                      title: 'de cargas por cada peso de aumento.',
                      detail: `${LIST.format(charges.map((step) => chargeName(step.label)))}.`,
                  },
              ]),
    ];
}

function raiseFinding(report: ScenarioReport): Finding[] {
    const raises = report.raises.map((raise) => raise.monthly_raise_cents);

    if (raises.length === 0) {
        return [];
    }

    const spread = Math.max(...raises) - Math.min(...raises);
    const amount = report.scenario.raise_unit === 'amount';

    if (spread === 0) {
        return [
            {
                figure: wholePesos(raises[0] ?? 0),
                title: 'al mes recibe cada persona.',
                detail: amount
                    ? 'Un monto fijo da lo mismo a cada quien, gane lo que gane.'
                    : 'Todas las categorías reciben lo mismo.',
            },
        ];
    }

    return [
        {
            figure: wholePesos(spread),
            title: 'al mes separan al que más y al que menos recibe.',
            detail: amount
                ? 'Cada grupo recibe su propio monto fijo.'
                : 'Un porcentaje igual da más pesos a quien ya gana más.',
        },
    ];
}

/** «Hallazgos clave»: three things to read first, in plain words and computed from the numbers; what cannot be said is left out. */
export function findings(report: ScenarioReport): Finding[] {
    return [...costFindings(report), ...raiseFinding(report)];
}
