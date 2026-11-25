import { describe, expect, it } from 'vite-plus/test';
import { amountText, percentText, wholePesos } from '@/lib/compensation/money';
import { figureText, summaryFigures } from '@/lib/compensation/report';
import {
    annexNote,
    reportAccuracy,
    reportSections,
    premiumNotice,
    reportSubtitle,
} from '@/lib/compensation/report-notes';
import { boardReport } from '../../support/compensation-report';

describe('wholePesos', () => {
    it('writes cents as whole pesos, a half going up', () => {
        expect(wholePesos(102_367_592)).toBe('$1,023,676');
        expect(wholePesos(50)).toBe('$1');
        expect(wholePesos(-100_000)).toBe('-$1,000');
    });
});

describe('amountText', () => {
    it('writes an amount as typed, with cents only when it has them', () => {
        expect(amountText('608')).toBe('$608');
        expect(amountText('608.5')).toBe('$608.50');
        expect(amountText('608.25')).toBe('$608.25');
        expect(amountText('nada')).toBe('$0');
    });
});

describe('percentText', () => {
    it('writes a share with one decimal at most, and nothing of nothing', () => {
        expect(percentText(1, 3)).toBe('33.3 %');
        expect(percentText(1, 0)).toBe('0 %');
    });
});

describe('summaryFigures', () => {
    it('gives the four figures of the board, each with what it means', () => {
        expect(summaryFigures(boardReport(), false)).toEqual([
            {
                key: 'first_year',
                caption: 'Costo del primer año',
                value: 1_023_676,
                unit: 'pesos',
                note: 'Incluye $146,207 de 2 meses de retroactivo',
            },
            {
                key: 'recurring',
                caption: 'Al año, al arranque',
                value: 877_469,
                unit: 'pesos',
                note: 'Los años siguientes cambian: mira la proyección',
            },
            {
                key: 'payroll',
                caption: 'Nómina con cargas',
                value: 46,
                unit: 'tenths',
                note: 'De $19.22 a $20.10 millones al año',
            },
            {
                key: 'per_person',
                caption: 'Por persona al mes',
                value: 812,
                unit: 'pesos',
                note: 'Costo promedio para la empresa, 90 personas',
            },
        ]);
    });

    it('says when there is no retroactivity, writes a small payroll in pesos and counts a union in agremiados', () => {
        const report = boardReport();
        report.headline = {
            ...report.headline,
            retroactive_months: 0,
            before_cents: 50_000_000,
            after_cents: 52_500_000,
            recurring_cents: 2_500_000,
            people: 1,
        };
        const figures = summaryFigures(report, true);

        expect(figures[0]?.note).toBe('Sin retroactivo');
        expect(figures[2]).toMatchObject({
            value: 50,
            note: 'De $500,000 a $525,000 al año',
        });
        expect(figures[3]?.note).toBe(
            'Costo promedio para la empresa, 1 agremiado',
        );
    });

    it('draws zeros, not a division by zero, when nobody is costed', () => {
        const report = boardReport();
        report.headline = {
            ...report.headline,
            people: 0,
            before_cents: 0,
            after_cents: 0,
            recurring_cents: 0,
        };
        const figures = summaryFigures(report, false);

        expect(figures[2]?.value).toBe(0);
        expect(figures[3]?.value).toBe(0);
    });
});

describe('figureText', () => {
    it('writes a figure as pesos or as a signed percent with one decimal', () => {
        expect(figureText('pesos', 1_023_676)).toBe('$1,023,676');
        expect(figureText('tenths', 46)).toBe('+4.6 %');
        expect(figureText('tenths', 50)).toBe('+5 %');
        expect(figureText('tenths', -12)).toBe('-1.2 %');
        expect(figureText('tenths', 0)).toBe('0 %');
    });
});

describe('reportAccuracy', () => {
    it('counts what keeps the number from being exact, in one sentence (C6)', () => {
        expect(reportAccuracy(boardReport())).toEqual({
            missing: 2,
            title: 'Faltan 2 datos para que el número sea exacto:',
            detail: '1 persona sin datos completos en el padrón y tu prima de riesgo de trabajo.',
        });
    });

    it('names the categories without salary and the state of the people, and says one datum in singular', () => {
        const report = boardReport();
        report.coverage = { ...report.coverage, costed: 91 };
        report.warnings = {
            ...report.warnings,
            uncalculated: ['Impuesto sobre nómina'],
            uncosted: [{ label: 'Técnico · B', people: 2 }],
        };

        expect(reportAccuracy(report)?.detail).toBe(
            '1 categoría sin salario en el tabulador y el estado de las personas, para el impuesto sobre nómina.',
        );
        report.warnings = {
            ...report.warnings,
            uncalculated: ['Otra cuota'],
            uncosted: [],
        };
        expect(reportAccuracy(report)).toEqual({
            missing: 1,
            title: 'Falta 1 dato para que el número sea exacto:',
            detail: 'otra cuota.',
        });
    });

    it('has nothing to say when every datum is there', () => {
        const report = boardReport();
        report.coverage = { ...report.coverage, costed: 91 };
        report.warnings = { ...report.warnings, uncalculated: [] };

        expect(reportAccuracy(report)).toBeNull();
    });
});

describe('annexNote', () => {
    it('says who is costed, and the fiscal values and individual salaries when there are', () => {
        const report = boardReport();
        expect(annexNote(report)).toBe('90 de 91 personas costeadas.');

        report.warnings = {
            ...report.warnings,
            stale: ['UMA', 'Salario mínimo'],
            individual: { count: 12, percent: '13.3', over_tenth: true },
        };
        expect(annexNote(report)).toBe(
            '90 de 91 personas costeadas. Aún no se publican algunos valores fiscales (UMA, Salario mínimo); se usa el último que se conoce. 12 personas tienen sueldo individual (13.3 % de la plantilla costeada). Son más de la décima parte: es probable que el tabulador no describa la realidad.',
        );
    });
});

describe('reportSections', () => {
    it('numbers the parts after the summary, with or without the other proposals', () => {
        expect(
            reportSections(true).map((section) => [
                section.id,
                section.label,
                section.title,
            ]),
        ).toEqual([
            ['resumen', 'Resumen', 'Resumen'],
            ['costo', 'De dónde sale', '1. De dónde sale el costo'],
            ['quien', 'Quién recibe', '2. Quién recibe el aumento'],
            ['anios', 'Próximos años', '3. Los próximos años'],
            ['frente', 'Frente a otras', '4. Frente a las otras propuestas'],
            ['anexo', 'Anexo', '5. Anexo: detalle por concepto'],
        ]);
        expect(
            reportSections(false)
                .map((section) => section.title)
                .at(-1),
        ).toBe('4. Anexo: detalle por concepto');
    });
});

describe('reportSubtitle', () => {
    it('says whose, since when, what it raises and its retroactivity', () => {
        expect(reportSubtitle(boardReport(), 'Autopartes Demo')).toBe(
            'Autopartes Demo · desde el 1 de enero de 2027 · 5 % sindicalizados · 3 % confianza · 2 meses de retroactivo',
        );
        expect(
            reportSubtitle(
                {
                    ...boardReport(),
                    scenario: {
                        ...boardReport().scenario,
                        retroactive_months: 0,
                        effective_on: 'no',
                    },
                },
                null,
            ),
        ).toBe('5 % sindicalizados · 3 % confianza · sin retroactivo');
    });
});

describe('reportSubtitle of an average payroll', () => {
    const average = {
        workers: 500,
        monthly_salary_text: '$15,000.00',
        tenure_years: 1,
        group: 'Sindicalizados',
    };
    const report = (workers: number) => ({
        ...boardReport(),
        scenario: {
            ...boardReport().scenario,
            source: 'average' as const,
            average: { ...average, workers },
            retroactive_months: 0,
        },
    });

    it('says what the payroll is made of right after whose it is', () => {
        expect(reportSubtitle(report(500), 'Autopartes Demo')).toBe(
            'Autopartes Demo · plantilla promedio de 500 personas a $15,000.00 al mes · desde el 1 de enero de 2027 · 5 % sindicalizados · 3 % confianza · sin retroactivo',
        );
        expect(reportSubtitle(report(1), null)).toContain(
            'plantilla promedio de 1 persona a $15,000.00 al mes',
        );
    });
});

describe('reportAccuracy of an average payroll', () => {
    it('asks for the state where they work, not for the state of the people', () => {
        const report = boardReport();
        report.coverage = { ...report.coverage, costed: 91 };
        report.warnings = {
            ...report.warnings,
            uncalculated: ['Impuesto sobre nómina'],
        };
        report.scenario = {
            ...report.scenario,
            source: 'average',
            average: {
                workers: 91,
                monthly_salary_text: '$15,000.00',
                tenure_years: 1,
                group: 'Sindicalizados',
            },
        };

        expect(reportAccuracy(report)?.detail).toBe(
            'el estado donde trabajan, para el impuesto sobre nómina.',
        );
    });
});

describe('premiumNotice', () => {
    it('says that the premium of work risk is the one of class I, and that the real one may be higher', () => {
        const report = boardReport();

        expect(premiumNotice(report)).toBeNull();
        expect(
            premiumNotice({
                ...report,
                warnings: { ...report.warnings, class_one_premium: true },
            }),
        ).toEqual({
            title: 'Riesgo de trabajo con la prima media de clase I.',
            detail: 'Tu empresa aún no captura la suya: la real puede ser mayor y subir el costo.',
        });
    });
});
