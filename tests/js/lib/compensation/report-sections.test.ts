import { describe, expect, it } from 'vite-plus/test';
import { costGroups, unchangedNote } from '@/lib/compensation/report-costs';
import { EVALUATION_POINTS } from '@/lib/compensation/report-evaluate';
import {
    ladderRows,
    otherRows,
    pointNote,
} from '@/lib/compensation/report-scenarios';
import { boardReport, concept } from '../../support/compensation-report';

describe('costGroups', () => {
    it('shares the first year among its sources, the aguinaldo and the premium as one, each with why it moves', () => {
        const report = boardReport();
        report.headline = { ...report.headline, first_year_cents: 1_000 };
        report.concepts = [
            concept({ type: 'salary', key: 'salary', label: 'Salario' }, 600),
            concept(
                { type: 'benefit', key: 'christmas_bonus', label: 'Aguinaldo' },
                100,
            ),
            concept(
                {
                    type: 'benefit',
                    key: 'vacation_premium',
                    label: 'Prima vacacional',
                },
                50,
            ),
            concept(
                {
                    type: 'contribution',
                    key: 'imss_cash',
                    label: 'IMSS: prestaciones en dinero',
                },
                150,
            ),
            concept(
                { type: 'contribution', key: 'infonavit', label: 'Infonavit' },
                100,
            ),
        ];

        expect(
            costGroups(report).map((group) => [
                group.label,
                group.shareText,
                group.note,
            ]),
        ).toEqual([
            ['Salario', '60 %', 'El aumento mismo.'],
            [
                'Cuotas del IMSS',
                '15 %',
                'Suben con el nuevo SBC; la cuota fija no.',
            ],
            [
                'Aguinaldo y prima vacacional',
                '15 %',
                'Suben porque se pagan sobre el salario diario.',
            ],
            ['Infonavit', '10 %', 'Se calcula sobre el nuevo SBC.'],
        ]);
    });

    it('explains a benefit of the contract and the payroll tax, and has nothing without aguinaldo', () => {
        const report = boardReport();
        report.headline = { ...report.headline, first_year_cents: 300 };
        report.concepts = [
            concept(
                {
                    type: 'benefit',
                    key: 'savings_fund',
                    label: 'Fondo de ahorro',
                },
                200,
            ),
            concept(
                {
                    type: 'contribution',
                    key: 'payroll_tax',
                    label: 'Impuesto sobre nómina',
                },
                100,
            ),
        ];

        expect(costGroups(report).map((group) => group.note)).toEqual([
            'Prestación del contrato que sube con el salario.',
            'Se calcula sobre la nómina gravable.',
        ]);
    });
});

describe('unchangedNote', () => {
    it('says that the fixed quota of the IMSS does not move, with what it costs a year', () => {
        const report = boardReport();
        report.concepts = [
            {
                ...concept(
                    {
                        type: 'contribution',
                        key: 'imss_fixed',
                        label: 'IMSS: cuota fija',
                    },
                    0,
                ),
                after_text: '$4,212,200.00',
            },
        ];

        expect(unchangedNote(report)).toBe(
            'No sube con el aumento: la cuota fija del IMSS, $4,212,200 al año, porque se calcula con la UMA y no con el salario.',
        );
        expect(unchangedNote(boardReport())).toBeNull();
    });
});

describe('scenario rows', () => {
    it('puts the ladder with its bars against the dearest rung and the current one named', () => {
        const report = boardReport();
        report.ladder = [
            {
                raise_text: '4 %',
                current: false,
                first_year_cents: 30_000_000,
                recurring_cents: 30_000_000,
                salary_growth_bp: 400,
                payroll_growth_bp: 382,
                per_person_month_cents: 80_000,
            },
            {
                raise_text: '6 %',
                current: true,
                first_year_cents: 60_000_000,
                recurring_cents: 60_000_000,
                salary_growth_bp: 600,
                payroll_growth_bp: 582,
                per_person_month_cents: 114_300,
            },
        ];

        expect(ladderRows(report)).toEqual([
            {
                key: '4 %',
                name: '4 %',
                raiseText: '4 %',
                costText: '$300,000',
                percent: 50,
                growthText: '+3.82 %',
                perPersonText: '$800',
                current: false,
            },
            {
                key: '6 %',
                name: '6 % (esta)',
                raiseText: '6 %',
                costText: '$600,000',
                percent: 100,
                growthText: '+5.82 %',
                perPersonText: '$1,143',
                current: true,
            },
        ]);
    });

    it('weighs this proposal against the others, the cheapest first, this one named', () => {
        const rows = otherRows(boardReport());

        expect(
            rows.map((row) => [row.name, row.costText, row.current]),
        ).toEqual([
            ['Revisión 2027 · 4 %', '$710,062', false],
            ['Revisión 2027 · 5 % (esta)', '$1,023,676', true],
        ]);
        expect(rows[1]).toMatchObject({
            growthText: '+4.57 %',
            perPersonText: '$812',
            percent: 100,
        });
        expect(rows[0]?.raiseText).toBe('4 % sindicalizados · 2 % confianza');
    });

    it('says what a point costs', () => {
        const report = boardReport();
        report.sensitivity = {
            ...report.sensitivity,
            one_point_text: '$1,171,275.00',
        };

        expect(pointNote(report)).toBe(
            'Cada punto de aumento al salario cuesta $1,171,275 en el primer año, con las cuotas incluidas.',
        );
    });
});

describe('EVALUATION_POINTS', () => {
    it('are the six things a raise is weighed against, with no figure of ours', () => {
        expect(EVALUATION_POINTS.map((point) => point.key)).toEqual([
            'compression',
            'market',
            'overtime',
            'benefits',
            'capacity',
            'productivity',
        ]);
        expect(
            EVALUATION_POINTS.some((point) => /\$|\d/.test(point.detail)),
        ).toBe(false);
    });
});
