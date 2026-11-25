import { describe, expect, it } from 'vite-plus/test';
import { baseFacts, reportSteps } from '@/lib/compensation/report-steps';
import { boardReport, concept } from '../../support/compensation-report';
import type { ReportExample, ScenarioReport } from '@/types';

const example: ReportExample = {
    workers: 500,
    monthly_payroll_cents: 750_000_000,
    monthly_salary_cents: 1_500_000,
    monthly_raise_cents: 90_000,
    aguinaldo_days: '15',
    vacation_days: '12',
    vacation_premium_percent: '25',
    salary_extra_year_cents: 1_080_000,
    bonus_extra_year_cents: 45_000,
    premium_extra_year_cents: 9_000,
    factor: '1.0493',
    sbc_before_cents: 52_465,
    sbc_after_cents: 55_614,
    contributions_extra_year_cents: 237_300,
    total_extra_year_cents: 1_371_300,
    total_extra_month_cents: 114_275,
};

function average(): ScenarioReport {
    const report = boardReport();
    report.example = example;
    report.headline = {
        ...report.headline,
        people: 500,
        retroactive_months: 0,
        retroactive_cents: 0,
        recurring_cents: 685_650_000,
        first_year_cents: 685_650_000,
    };

    return report;
}

describe('reportSteps of an average payroll', () => {
    it('walks one worker from the raise to the total, each step with what goes in and what comes out', () => {
        const steps = reportSteps(average());

        expect(steps.map((step) => step.key)).toEqual([
            'raise',
            'benefits',
            'sbc',
            'quotas',
            'integral',
            'total',
        ]);
        expect(steps[0]?.result.value).toBe('$15,900 (+$900)');
        expect(steps[1]?.lines.map((line) => line.label)).toEqual([
            'Aguinaldo, 15 días',
            'Prima vacacional, 12 días al 25 %',
        ]);
        expect(steps[1]?.result.value).toBe('+$540 al año');
        expect(steps[2]).toMatchObject({
            lines: [
                { label: 'Factor de integración', value: '1.0493' },
                { label: 'SBC diario antes', value: '$524.65' },
            ],
            result: { value: '$556.14 (+$31.49)' },
        });
        expect(steps[3]?.result.value).toBe('+$198 al mes');
        expect(steps[4]?.result.value).toBe('+$13,713 al año ($1,143 al mes)');
        expect(steps[5]?.result).toEqual({
            label: 'Costo del primer año',
            value: '$6,856,500',
        });
    });

    it('adds the retroactive months to the total', () => {
        const report = average();
        report.headline = {
            ...report.headline,
            retroactive_months: 2,
            retroactive_cents: 10_000_000,
        };
        const total = reportSteps(report).at(-1);

        expect(total?.lines).toEqual([
            { label: 'Personas', value: '500' },
            { label: 'Costo integral al año', value: '$6,856,500' },
            { label: 'Retroactivo de 2 meses', value: '$100,000' },
        ]);
    });
});

describe('reportSteps of the roster', () => {
    it('has no worker to stand for them all: the steps are the sums of what each concept adds', () => {
        const report = boardReport();
        report.concepts = [
            concept(
                { type: 'salary', key: 'salary', label: 'Salario' },
                900,
                800,
            ),
            concept(
                { type: 'benefit', key: 'aguinaldo', label: 'Aguinaldo' },
                90,
                80,
            ),
            concept(
                { type: 'contribution', key: 'infonavit', label: 'Infonavit' },
                60,
                50,
            ),
        ];
        const steps = reportSteps(report);

        expect(steps.map((step) => step.key)).toEqual([
            'raise',
            'benefits',
            'quotas',
            'integral',
            'total',
        ]);
        expect(steps[0]?.result.value).toBe('+$8 al año');
        expect(steps[1]?.result.value).toBe('+$1 al año');
        expect(steps[2]?.result.value).toBe('+$1 al año');
        expect(steps[3]?.result.value).toBe('$812 al mes (90 personas)');
        expect(steps.at(-1)?.result.value).toBe('$1,023,676');
    });
});

describe('baseFacts', () => {
    it('opens the walk with the base example: the payroll, the raise and the benefits of the law', () => {
        expect(
            baseFacts(average())?.map((fact) => [fact.label, fact.value]),
        ).toEqual([
            ['Trabajadores', '500'],
            ['Nómina mensual', '$7,500,000'],
            ['Salario promedio', '$15,000'],
            ['Aumento propuesto', '5 % sindicalizados · 3 % confianza'],
            ['Aguinaldo', '15 días'],
            ['Vacaciones', '12 días'],
            ['Prima vacacional', '25 %'],
        ]);
    });

    it('has no base example for the roster', () => {
        expect(baseFacts(boardReport())).toBeNull();
    });
});
