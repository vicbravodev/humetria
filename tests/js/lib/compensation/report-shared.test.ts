import { describe, expect, it } from 'vite-plus/test';
import { sharedNotice } from '@/lib/compensation/report-notes';
import { boardReport } from '../../support/compensation-report';

describe('sharedNotice', () => {
    const roster = boardReport();
    const average = {
        ...roster,
        scenario: {
            ...roster.scenario,
            source: 'average' as const,
            average: {
                workers: 500,
                monthly_salary_text: '$15,000.00',
                tenure_years: 1,
                group: 'Sindicalizados',
            },
        },
    };

    it('says who shared it, when it ends and in what zone', () => {
        expect(
            sharedNotice(roster, 'Acme', {
                expiresAt: '27 nov 2026, 20:58',
                minimum: 5,
            }),
        ).toContain(
            'Acme compartió este reporte contigo. Vence el 27 nov 2026, 20:58 (hora de la Ciudad de México).',
        );
    });

    it('names the minimum the company really applies when the groups are folded into Otros', () => {
        expect(
            sharedNotice(roster, 'Acme', { expiresAt: 'mañana', minimum: 9 }),
        ).toContain(
            'Por privacidad, los grupos con menos de 9 personas se muestran juntos como «Otros».',
        );
    });

    it('promises no folding where nothing is folded: with a minimum of one', () => {
        expect(
            sharedNotice(roster, 'Acme', { expiresAt: 'mañana', minimum: 1 }),
        ).not.toContain('Otros');
        expect(
            sharedNotice(roster, 'Acme', {
                expiresAt: 'mañana',
                minimum: null,
            }),
        ).not.toContain('Otros');
    });

    it('says an average payroll is an estimate with no person in it, and never speaks of Otros', () => {
        const text = sharedNotice(average, 'Acme', {
            expiresAt: 'mañana',
            minimum: 5,
        });

        expect(text).toContain(
            'Es una estimación sobre una plantilla promedio de 500 personas y no incluye datos de ninguna persona.',
        );
        expect(text).not.toContain('Otros');
    });
});
