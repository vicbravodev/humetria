import { describe, expect, it } from 'vite-plus/test';
import { findings } from '@/lib/compensation/report';
import { boardReport, concept } from '../../support/compensation-report';

describe('findings', () => {
    it('says in plain words where the cost goes, what each peso drags and how far apart the raises are', () => {
        expect(findings(boardReport())).toEqual([
            {
                figure: '3 de 4',
                title: 'pesos del costo van al salario.',
                detail: 'El resto son cuotas y prestaciones que suben solas con él.',
            },
            {
                figure: '34 ¢',
                title: 'de cargas por cada peso de aumento.',
                detail: 'IMSS, Infonavit, aguinaldo, impuesto sobre nómina y prima vacacional.',
            },
            {
                figure: '$171',
                title: 'al mes separan al que más y al que menos recibe.',
                detail: 'Un porcentaje igual da más pesos a quien ya gana más.',
            },
        ]);
    });

    it('counts in tenths when the share is far from a quarter', () => {
        const report = boardReport();
        report.concepts = [
            concept({ type: 'salary', key: 'salary', label: 'Salario' }, 900),
            concept(
                { type: 'contribution', key: 'infonavit', label: 'Infonavit' },
                100,
            ),
        ];
        report.headline = { ...report.headline, first_year_cents: 1_000 };

        expect(findings(report)[0]?.figure).toBe('9 de 10');
    });

    it('says everyone receives the same with a fixed amount, and leaves out what it cannot say', () => {
        const report = boardReport();
        report.scenario = { ...report.scenario, raise_unit: 'amount' };
        report.concepts = [
            concept(
                { type: 'benefit', key: 'vouchers', label: 'Vales' },
                5_000,
            ),
        ];
        report.raises = [
            {
                label: 'Operador · A',
                group: 'unionized',
                people: 9,
                monthly_raise_cents: 60_800,
            },
            {
                label: 'Operador · B',
                group: 'unionized',
                people: 3,
                monthly_raise_cents: 60_800,
            },
        ];

        expect(findings(report)).toEqual([
            {
                figure: '$608',
                title: 'al mes recibe cada persona.',
                detail: 'Un monto fijo da lo mismo a cada quien, gane lo que gane.',
            },
        ]);
    });

    it('explains a fixed amount that still differs by group', () => {
        const report = boardReport();
        report.scenario = { ...report.scenario, raise_unit: 'amount' };
        report.raises = [
            {
                label: 'Operador · A',
                group: 'unionized',
                people: 9,
                monthly_raise_cents: 60_800,
            },
            {
                label: 'Supervisor · A',
                group: 'trusted',
                people: 3,
                monthly_raise_cents: 30_000,
            },
        ];

        expect(findings(report)[2]).toEqual({
            figure: '$308',
            title: 'al mes separan al que más y al que menos recibe.',
            detail: 'Cada grupo recibe su propio monto fijo.',
        });
    });

    it('says nothing of the raises when no category is shown', () => {
        const report = boardReport();
        report.raises = [];

        expect(findings(report)).toHaveLength(2);
    });
});
