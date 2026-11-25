import { describe, expect, it } from 'vite-plus/test';
import {
    figureText,
    summaryFigures,
    verdictSentence,
} from '@/lib/compensation/report';
import { openCaveats, reportCaveats } from '@/lib/compensation/report-caveats';
import { boardReport } from '../../support/compensation-report';

describe('summaryFigures', () => {
    it('gives the four anchors: the cost, what the payroll really grows, a person a month and each peso dragged', () => {
        expect(summaryFigures(boardReport(), false)).toEqual([
            {
                key: 'first_year',
                caption: 'Cuesta el primer año',
                value: 1_023_676,
                unit: 'pesos',
                note: 'Incluye $146,207 de 2 meses de retroactivo',
            },
            {
                key: 'payroll',
                caption: 'Sube tu nómina',
                value: 457,
                unit: 'percent',
                note: 'El salario sube 5 %; la cuota fija del IMSS no sube con él',
            },
            {
                key: 'per_person',
                caption: 'Por persona al mes',
                value: 812,
                unit: 'pesos',
                note: 'Costo extra para la empresa, 90 personas',
            },
            {
                key: 'drag',
                caption: 'Cada $1 de aumento cuesta',
                value: 119,
                unit: 'drag',
                note: 'Ya con aguinaldo, prima, IMSS e Infonavit',
            },
        ]);
    });

    it('says the year with no retroactivity, counts a union in agremiados and says the quotas move when the payroll grows as much', () => {
        const report = boardReport();
        report.headline = {
            ...report.headline,
            retroactive_months: 0,
            people: 1,
        };
        report.impact = { ...report.impact, payroll_growth_bp: 500 };
        const figures = summaryFigures(report, true);

        expect(figures[0]?.note).toBe('$877,469 al año sin retroactivo');
        expect(figures[1]?.note).toBe(
            'El salario sube 5 %; las cuotas y prestaciones suben con él',
        );
        expect(figures[2]?.note).toBe(
            'Costo extra para la empresa, 1 agremiado',
        );
    });
});

describe('figureText', () => {
    it('writes an anchor as pesos, a signed percent with two decimals or a peso with cents', () => {
        expect(figureText('pesos', 1_023_676)).toBe('$1,023,676');
        expect(figureText('percent', 582)).toBe('+5.82 %');
        expect(figureText('percent', 500)).toBe('+5.00 %');
        expect(figureText('percent', -120)).toBe('-1.20 %');
        expect(figureText('percent', 0)).toBe('0.00 %');
        expect(figureText('drag', 127)).toBe('$1.27');
    });
});

describe('verdictSentence', () => {
    it('says the cost, the real growth of the payroll against the salary and the raise against inflation', () => {
        expect(verdictSentence(boardReport(), false)).toEqual([
            'Este aumento cuesta $1,023,676 el primer año a 90 personas. Con prestaciones y cuotas, la nómina sube 4.57 %, frente al 5 % del salario.',
            'Frente a una inflación de 3 %, el salario gana 1.94 % de poder de compra.',
        ]);
    });

    it('says the salary loses buying power when the raise is under inflation', () => {
        const report = boardReport();
        report.impact = { ...report.impact, real_raise_bp: -120 };

        expect(verdictSentence(report, true)[1]).toBe(
            'Frente a una inflación de 3 %, el salario pierde 1.2 % de poder de compra.',
        );
    });

    it('asks for the data when nobody is costed', () => {
        const report = boardReport();
        report.headline = { ...report.headline, people: 0 };

        expect(verdictSentence(report, false)).toEqual([
            'Aún no hay personas costeadas: completa los datos del padrón.',
        ]);
    });
});

describe('reportCaveats', () => {
    it('lists what moves the number first, with its way to fix it, and what is never calculated after', () => {
        const report = boardReport();
        report.warnings = {
            ...report.warnings,
            class_one_premium: true,
            stale: ['UMA', 'Salario mínimo'],
        };
        const caveats = reportCaveats(report);

        expect(caveats.map((caveat) => [caveat.key, caveat.action])).toEqual([
            ['accuracy', 'complete'],
            ['premium', 'premium'],
            ['stale', null],
            ['rates', null],
            ['rounding', null],
            ['scope', null],
        ]);
        expect(caveats[2]?.detail).toBe(
            'Aún no se publican UMA y Salario mínimo; se usa el último que se conoce.',
        );
        expect(openCaveats(report)).toBe(2);
    });

    it('keeps only what is always said when the data is complete', () => {
        const report = boardReport();
        report.coverage = { ...report.coverage, costed: 91 };
        report.warnings = { ...report.warnings, uncalculated: [] };

        expect(reportCaveats(report).map((caveat) => caveat.key)).toEqual([
            'rates',
            'rounding',
            'scope',
        ]);
        expect(openCaveats(report)).toBe(0);
    });
});
