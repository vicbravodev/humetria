import { exactPesos, wholePesos } from '@/lib/compensation/money';
import { countOf } from '@/lib/plural';
import type { ReportConcept, ReportExample, ScenarioReport } from '@/types';

export type StepLine = { label: string; value: string };

/** One step of the cost, as the reader follows it: what goes in, and what comes out. */
export type ReportStep = {
    key: string;
    title: string;
    lines: StepLine[];
    result: StepLine;
};

function recurring(
    concepts: ReportConcept[],
    type: ReportConcept['type'],
): number {
    return concepts
        .filter((concept) => concept.type === type)
        .reduce((sum, concept) => sum + concept.recurring_cents, 0);
}

function total(report: ScenarioReport, title: string): ReportStep {
    const { headline } = report;
    const months = headline.retroactive_months;

    return {
        key: 'total',
        title,
        lines: [
            { label: 'Personas', value: String(headline.people) },
            {
                label: 'Costo integral al año',
                value: wholePesos(headline.recurring_cents),
            },
            ...(months > 0
                ? [
                      {
                          label: `Retroactivo de ${countOf(months, 'mes', 'meses')}`,
                          value: wholePesos(headline.retroactive_cents),
                      },
                  ]
                : []),
        ],
        result: {
            label: 'Costo del primer año',
            value: wholePesos(headline.first_year_cents),
        },
    };
}

/** An average worker, from the engine: the raise, what follows it in benefits, the SBC and its factor, the quotas, the sum. */
function exampleSteps(
    report: ScenarioReport,
    example: ReportExample,
): ReportStep[] {
    const monthly = example.monthly_salary_cents;
    const benefits =
        example.bonus_extra_year_cents + example.premium_extra_year_cents;
    const quotasMonth = Math.round(example.contributions_extra_year_cents / 12);

    return [
        {
            key: 'raise',
            title: 'Aumento salarial',
            lines: [
                { label: 'Salario mensual', value: wholePesos(monthly) },
                { label: 'Aumento', value: report.scenario.raise_text },
            ],
            result: {
                label: 'Nuevo salario mensual',
                value: `${wholePesos(monthly + example.monthly_raise_cents)} (+${wholePesos(example.monthly_raise_cents)})`,
            },
        },
        {
            key: 'benefits',
            title: 'Prestaciones vinculadas',
            lines: [
                {
                    label: `Aguinaldo, ${example.aguinaldo_days} días`,
                    value: `+${wholePesos(example.bonus_extra_year_cents)} al año`,
                },
                {
                    label: `Prima vacacional, ${example.vacation_days} días al ${example.vacation_premium_percent} %`,
                    value: `+${wholePesos(example.premium_extra_year_cents)} al año`,
                },
            ],
            result: {
                label: 'Prestaciones',
                value: `+${wholePesos(benefits)} al año`,
            },
        },
        {
            key: 'sbc',
            title: 'Nuevo SBC y factor de integración',
            lines: [
                { label: 'Factor de integración', value: example.factor },
                {
                    label: 'SBC diario antes',
                    value: exactPesos(example.sbc_before_cents),
                },
            ],
            result: {
                label: 'SBC diario nuevo',
                value: `${exactPesos(example.sbc_after_cents)} (+${exactPesos(example.sbc_after_cents - example.sbc_before_cents)})`,
            },
        },
        {
            key: 'quotas',
            title: 'Cuotas patronales adicionales',
            lines: [
                {
                    label: 'IMSS, retiro, cesantía e Infonavit con el SBC nuevo',
                    value: `+${wholePesos(example.contributions_extra_year_cents)} al año`,
                },
            ],
            result: {
                label: 'Cuotas adicionales',
                value: `+${wholePesos(quotasMonth)} al mes`,
            },
        },
        {
            key: 'integral',
            title: 'Costo integral por persona',
            lines: [
                {
                    label: 'Salario',
                    value: `+${wholePesos(example.salary_extra_year_cents)} al año`,
                },
                {
                    label: 'Prestaciones',
                    value: `+${wholePesos(benefits)} al año`,
                },
                {
                    label: 'Cuotas',
                    value: `+${wholePesos(example.contributions_extra_year_cents)} al año`,
                },
            ],
            result: {
                label: 'Por persona',
                value: `+${wholePesos(example.total_extra_year_cents)} al año (${wholePesos(example.total_extra_month_cents)} al mes)`,
            },
        },
        total(report, 'Total de la plantilla'),
    ];
}

/** A payroll of the roster: no worker stands for them all, so the steps are the sums of what each concept adds. */
function rosterSteps(report: ScenarioReport): ReportStep[] {
    const { concepts, impact, headline } = report;
    const salary = recurring(concepts, 'salary');
    const benefits = recurring(concepts, 'benefit');
    const quotas = recurring(concepts, 'contribution');
    const year = (cents: number): string => `+${wholePesos(cents)} al año`;

    return [
        {
            key: 'raise',
            title: 'Aumento salarial',
            lines: [{ label: 'Aumento', value: report.scenario.raise_text }],
            result: { label: 'Salario de la plantilla', value: year(salary) },
        },
        {
            key: 'benefits',
            title: 'Prestaciones vinculadas',
            lines: [
                {
                    label: 'Aguinaldo, prima vacacional y las que dependen del salario',
                    value: year(benefits),
                },
            ],
            result: { label: 'Prestaciones', value: year(benefits) },
        },
        {
            key: 'quotas',
            title: 'Cuotas patronales adicionales',
            lines: [
                {
                    label: 'IMSS, retiro, cesantía, Infonavit e impuesto sobre nómina',
                    value: year(quotas),
                },
            ],
            result: { label: 'Cuotas adicionales', value: year(quotas) },
        },
        {
            key: 'integral',
            title: 'Costo integral',
            lines: [
                { label: 'Salario', value: year(salary) },
                { label: 'Prestaciones', value: year(benefits) },
                { label: 'Cuotas', value: year(quotas) },
            ],
            result: {
                label: 'Por persona',
                value: `${wholePesos(impact.per_person_month_cents)} al mes (${countOf(headline.people, 'persona', 'personas')})`,
            },
        },
        total(report, 'Total de la plantilla'),
    ];
}

/** «El cálculo paso a paso»: from the raise to the cost of the first year, each step with what it takes and what it gives. */
export function reportSteps(report: ScenarioReport): ReportStep[] {
    return report.example === null
        ? rosterSteps(report)
        : exampleSteps(report, report.example);
}

/** «Ejemplo base»: what the walk starts from (the payroll, the raise and the benefits of the law the worker has); none for the roster. */
export function baseFacts(report: ScenarioReport): StepLine[] | null {
    const { example } = report;

    return example === null
        ? null
        : [
              { label: 'Trabajadores', value: String(example.workers) },
              {
                  label: 'Nómina mensual',
                  value: wholePesos(example.monthly_payroll_cents),
              },
              {
                  label: 'Salario promedio',
                  value: wholePesos(example.monthly_salary_cents),
              },
              { label: 'Aumento propuesto', value: report.scenario.raise_text },
              {
                  label: 'Aguinaldo',
                  value: `${example.aguinaldo_days} días`,
              },
              {
                  label: 'Vacaciones',
                  value: `${example.vacation_days} días`,
              },
              {
                  label: 'Prima vacacional',
                  value: `${example.vacation_premium_percent} %`,
              },
          ];
}
