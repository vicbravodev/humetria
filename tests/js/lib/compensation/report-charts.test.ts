import { describe, expect, it } from 'vite-plus/test';
import {
    compareIds,
    costSteps,
    fixedAlternative,
    otherBars,
    raiseGroups,
    waterfallBars,
    yearColumns,
} from '@/lib/compensation/report-charts';
import { boardReport, concept } from '../../support/compensation-report';

describe('costSteps', () => {
    it('joins the IMSS lines, retirement and cesantía, leaves out what costs nothing and goes from the salary to the smallest', () => {
        expect(costSteps(boardReport().concepts)).toEqual([
            { key: 'salary', label: 'Salario', cents: 76_354_905 },
            { key: 'imss', label: 'Cuotas del IMSS', cents: 15_100_090 },
            {
                key: 'contribution:infonavit',
                label: 'Infonavit',
                cents: 4_040_750,
            },
            { key: 'benefit:aguinaldo', label: 'Aguinaldo', cents: 3_137_899 },
            {
                key: 'contribution:payroll_tax',
                label: 'Impuesto sobre nómina',
                cents: 2_424_063,
            },
            {
                key: 'benefit:vacation_premium',
                label: 'Prima vacacional',
                cents: 1_309_885,
            },
        ]);
    });

    it('puts a benefit that lowers the cost last', () => {
        const steps = costSteps([
            concept({ type: 'benefit', key: 'vouchers', label: 'Vales' }, -500),
            concept({ type: 'salary', key: 'salary', label: 'Salario' }, 1_000),
            concept(
                {
                    type: 'contribution',
                    key: 'work_risk',
                    label: 'IMSS: riesgo de trabajo',
                },
                200,
            ),
        ]);

        expect(steps.map((step) => step.key)).toEqual([
            'salary',
            'imss',
            'benefit:vouchers',
        ]);
    });
});

describe('waterfallBars', () => {
    it('stacks each concept to the first year, with its share in the tooltip', () => {
        const bars = waterfallBars(boardReport());

        expect(bars[0]).toEqual({
            key: 'salary',
            label: 'Salario',
            valueText: '$763,549',
            tooltip: 'Salario: $763,549 · 74.6 % del primer año',
            from: 0,
            to: 76_354_905 / 102_367_592,
            total: false,
        });
        expect(bars.at(-1)).toEqual({
            key: 'total',
            label: 'Primer año',
            valueText: '$1,023,676',
            tooltip: 'Total del primer año: $1,023,676',
            from: 0,
            to: 1,
            total: true,
        });
        expect(bars).toHaveLength(7);
    });

    it('gives no share of a first year that costs nothing', () => {
        const report = boardReport();
        report.concepts = [
            concept({ type: 'salary', key: 'salary', label: 'Salario' }, 0),
        ];
        report.headline = { ...report.headline, first_year_cents: 0 };

        expect(waterfallBars(report)).toEqual([
            {
                key: 'total',
                label: 'Primer año',
                valueText: '$0',
                tooltip: 'Total del primer año: $0',
                from: 0,
                to: 0,
                total: true,
            },
        ]);
    });
});

describe('raiseGroups', () => {
    it('groups the categories by sindicalizados and confianza on one scale of hundreds of pesos', () => {
        const { groups, scaleText } = raiseGroups(boardReport(), false);

        expect(scaleText).toBe(
            'Escala común de $0 a $600 al mes. Sueldo mensual = salario diario × 30.4.',
        );
        expect(
            groups.map((group) => [group.key, group.title, group.people]),
        ).toEqual([
            ['unionized', 'Sindicalizados · 5 %', '68 personas'],
            ['trusted', 'Confianza · 3 %', '22 personas'],
        ]);
        expect(groups[0]?.rows[0]).toEqual({
            key: 'Operador · B',
            label: 'Operador · B',
            people: '22 personas',
            valueText: '+$551',
            tooltip: 'Operador · B: +$551 al mes (5 %)',
            percent: 91.8,
        });
    });

    it('copies the raise of the sindicalizados to confianza, writes a fixed amount, counts agremiados and drops an empty group', () => {
        const report = boardReport();
        report.scenario = { ...report.scenario, trusted_raise: null };
        expect(raiseGroups(report, false).groups[1]?.title).toBe(
            'Confianza · 5 %',
        );

        report.scenario = {
            ...report.scenario,
            raise_unit: 'amount',
            unionized_raise: '608.5',
        };
        report.raises = [
            {
                label: 'Operador · A',
                group: 'unionized',
                people: 1,
                monthly_raise_cents: 60_850,
            },
        ];
        const { groups } = raiseGroups(report, true);

        expect(groups).toHaveLength(1);
        expect(groups[0]).toMatchObject({
            title: 'Sindicalizados · $608.50 al mes',
            people: '1 agremiado',
        });
        expect(groups[0]?.rows[0]?.tooltip).toBe(
            'Operador · A: +$609 al mes (monto fijo)',
        );
    });
});

describe('fixedAlternative', () => {
    it('finds the fixed amount that costs the same in salary, and what the lowest paid would receive more', () => {
        expect(fixedAlternative(boardReport())).toEqual({
            amountText: '$508',
            lowest: 'Operador · A',
            moreText: '$29',
            query: {
                name: 'Revisión 2027 · 5 % · monto fijo',
                raise_unit: 'amount',
                unionized_raise: '508',
                trusted_raise: '454',
                retroactive_months: 2,
            },
        });
    });

    it('leaves the confianza empty when nobody of confianza is costed', () => {
        const report = boardReport();
        report.raises = report.raises.filter(
            (raise) => raise.group === 'unionized',
        );

        expect(fixedAlternative(report)?.query.trusted_raise).toBe('');
    });

    it('offers nothing for a fixed amount already, one category alone, or a raise that is already even', () => {
        const amount = boardReport();
        amount.scenario = { ...amount.scenario, raise_unit: 'amount' };
        const alone = boardReport();
        alone.raises = alone.raises.slice(0, 1);
        const even = boardReport();
        even.raises = [
            {
                label: 'Operador · A',
                group: 'unionized',
                people: 2,
                monthly_raise_cents: 50_000,
            },
            {
                label: 'Operador · B',
                group: 'unionized',
                people: 2,
                monthly_raise_cents: 50_000,
            },
        ];

        expect(fixedAlternative(amount)).toBeNull();
        expect(fixedAlternative(alone)).toBeNull();
        expect(fixedAlternative(even)).toBeNull();
    });
});

describe('yearColumns', () => {
    it('draws each year of the projection against the dearest, the first with its retroactivity, and adds them up', () => {
        const { columns, totalText } = yearColumns(boardReport());

        expect(totalText).toBe('$3,013,620');
        expect(columns).toEqual([
            {
                key: '1',
                label: '2027',
                valueText: '$1,023,676',
                tooltip: '2027: $1,023,676 · incluye el retroactivo',
                percent: 98.8,
            },
            {
                key: '2',
                label: '2028',
                valueText: '$953,518',
                tooltip: '2028: $953,518',
                percent: 92,
            },
            {
                key: '3',
                label: '2029',
                valueText: '$1,036,426',
                tooltip: '2029: $1,036,426',
                percent: 100,
            },
        ]);
    });
});

describe('otherBars', () => {
    it('weighs this proposal against the others, from the cheapest, this one named', () => {
        expect(otherBars(boardReport())).toEqual([
            {
                key: 'scenario-0',
                name: 'Revisión 2027 · 4 %',
                text: '$710,062',
                percent: 69.4,
                current: false,
            },
            {
                key: 'current',
                name: 'Revisión 2027 · 5 % (esta)',
                text: '$1,023,676',
                percent: 100,
                current: true,
            },
        ]);
    });

    it('has nothing to weigh without others', () => {
        const report = boardReport();
        report.others = [];

        expect(otherBars(report)).toEqual([]);
    });
});

describe('compareIds', () => {
    it('compares this one with the first three others at most', () => {
        const report = boardReport();
        report.others = ['a', 'b', 'c', 'd'].map((id) => ({
            id,
            name: id,
            first_year_cents: 1,
            first_year_text: '$0',
        }));

        expect(compareIds(report)).toEqual(['scenario-1', 'a', 'b', 'c']);
    });
});
