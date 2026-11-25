import { describe, expect, it } from 'vite-plus/test';
import {
    amountText,
    bpText,
    exactPesos,
    percentText,
    wholePesos,
} from '@/lib/compensation/money';
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

describe('bpText and exactPesos', () => {
    it('writes basis points with the decimals they have and a figure of one person to the cent', () => {
        expect(bpText(582)).toBe('5.82');
        expect(bpText(600)).toBe('6');
        expect(bpText(194)).toBe('1.94');
        expect(exactPesos(52_465)).toBe('$524.65');
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
    const rows = (options: Parameters<typeof reportSections>[0]) =>
        reportSections(options).map((section) => [
            section.id,
            section.label,
            section.title,
        ]);

    it('numbers the parts after the summary, with everything a roster account can show', () => {
        expect(rows({ quien: true, ladder: true, others: true })).toEqual([
            ['resumen', 'Resumen', 'Resumen'],
            ['costo', 'De dónde sale', '1. De dónde sale el costo'],
            ['calculo', 'El cálculo', '2. El cálculo paso a paso'],
            ['quien', 'Quién recibe', '3. Quién recibe el aumento'],
            [
                'frente',
                'Otros niveles',
                '4. Frente a otros niveles y otras propuestas',
            ],
            ['anios', 'Próximos años', '5. Los próximos años'],
            ['evaluar', 'Qué más evaluar', '6. Qué más evaluar'],
            ['supuestos', 'Supuestos', '7. Supuestos y salvedades'],
            ['anexo', 'Anexo', '8. Anexo: detalle por concepto'],
        ]);
    });

    it('leaves out who receives it for an average payroll and the part that weighs against others when there is nothing to weigh', () => {
        expect(
            rows({ quien: false, ladder: false, others: false }).map(
                ([id]) => id,
            ),
        ).toEqual([
            'resumen',
            'costo',
            'calculo',
            'anios',
            'evaluar',
            'supuestos',
            'anexo',
        ]);
    });

    it('titles the part after what it weighs: the ladder, the proposals or both', () => {
        const title = (ladder: boolean, others: boolean) =>
            reportSections({ quien: false, ladder, others }).find(
                (section) => section.id === 'frente',
            );

        expect(title(true, false)?.title).toBe(
            '3. Frente a otros niveles de aumento',
        );
        expect(title(false, true)).toMatchObject({
            label: 'Otras propuestas',
            title: '3. Frente a otras propuestas',
        });
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
