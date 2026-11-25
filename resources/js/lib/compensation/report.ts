import { bpText, wholePesos } from '@/lib/compensation/money';
import { retroText } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

const PESOS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});
const PERCENT = new Intl.NumberFormat('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const DRAG = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

/** How an anchor of the summary is written as it counts up: whole pesos, hundredths of a percent, or hundredths of a peso. */
export type FigureUnit = 'pesos' | 'percent' | 'drag';

export type SummaryFigure = {
    key: string;
    caption: string;
    /** What it counts up to: whole pesos, hundredths of a percent, or hundredths of a peso. */
    value: number;
    unit: FigureUnit;
    note: string;
};

export function figureText(unit: FigureUnit, value: number): string {
    if (unit === 'pesos') {
        return PESOS.format(value);
    }

    return unit === 'percent'
        ? `${value > 0 ? '+' : ''}${PERCENT.format(value / 100)} %`
        : DRAG.format(value / 100);
}

function peopleText(people: number, union: boolean): string {
    return countOf(
        people,
        union ? 'agremiado' : 'persona',
        union ? 'agremiados' : 'personas',
    );
}

/** What the payroll grows says against the raise of the salary: less when the fixed quota of the IMSS does not move. */
function payrollNote(report: ScenarioReport): string {
    const { impact } = report;
    const salary = `${bpText(impact.salary_growth_bp)} %`;

    return impact.payroll_growth_bp < impact.salary_growth_bp
        ? `El salario sube ${salary}; la cuota fija del IMSS no sube con él`
        : `El salario sube ${salary}; las cuotas y prestaciones suben con él`;
}

/** The four anchors the report opens with: what it costs, what the payroll really grows, a person a month, and each peso dragged. */
export function summaryFigures(
    report: ScenarioReport,
    union: boolean,
): SummaryFigure[] {
    const { headline, impact } = report;
    const months = headline.retroactive_months;

    return [
        {
            key: 'first_year',
            caption: 'Cuesta el primer año',
            value: Math.round(headline.first_year_cents / 100),
            unit: 'pesos',
            note:
                months > 0
                    ? `Incluye ${wholePesos(headline.retroactive_cents)} de ${retroText(months)}`
                    : `${wholePesos(headline.recurring_cents)} al año sin retroactivo`,
        },
        {
            key: 'payroll',
            caption: 'Sube tu nómina',
            value: impact.payroll_growth_bp,
            unit: 'percent',
            note: payrollNote(report),
        },
        {
            key: 'per_person',
            caption: 'Por persona al mes',
            value: Math.round(impact.per_person_month_cents / 100),
            unit: 'pesos',
            note: `Costo extra para la empresa, ${peopleText(headline.people, union)}`,
        },
        {
            key: 'drag',
            caption: 'Cada $1 de aumento cuesta',
            value: impact.drag_hundredths,
            unit: 'drag',
            note: 'Ya con aguinaldo, prima, IMSS e Infonavit',
        },
    ];
}

/** The answer in a sentence: what it costs, how much the payroll really grows, and what it does against inflation. */
export function verdictSentence(
    report: ScenarioReport,
    union: boolean,
): string[] {
    const { headline, impact } = report;

    if (headline.people === 0) {
        return [
            'Aún no hay personas costeadas: completa los datos del padrón.',
        ];
    }

    const real = impact.real_raise_bp;
    const against = `Frente a una inflación de ${bpText(impact.inflation_bp)} %, el salario ${real < 0 ? 'pierde' : 'gana'} ${bpText(Math.abs(real))} % de poder de compra.`;

    return [
        `Este aumento cuesta ${wholePesos(headline.first_year_cents)} el primer año a ${peopleText(headline.people, union)}. Con prestaciones y cuotas, la nómina sube ${bpText(impact.payroll_growth_bp)} %, frente al ${bpText(impact.salary_growth_bp)} % del salario.`,
        against,
    ];
}
