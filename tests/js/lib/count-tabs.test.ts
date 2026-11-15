import { describe, expect, it } from 'vite-plus/test';
import { splitByAction } from '@/lib/count-tabs';

const tab = (
    label: string,
    count: number | undefined,
    extra: { active?: boolean; needsAction?: boolean } = {},
) => ({ label, count, active: false, ...extra });

describe('splitByAction', () => {
    it('puts what asks for action first, keeping the order within each group', () => {
        const { shown } = splitByAction([
            tab('Todas', 40),
            tab('Aprobadas', 30),
            tab('Pendientes', 4, { needsAction: true }),
            tab('Por vencer', 2, { needsAction: true }),
        ]);

        expect(shown.map((item) => item.label)).toEqual([
            'Pendientes',
            'Por vencer',
            'Todas',
            'Aprobadas',
        ]);
    });

    it('does not draw a tab of zero: it goes to the other filters', () => {
        const { shown, hidden } = splitByAction([
            tab('Pendientes', 0, { needsAction: true }),
            tab('Todas', 40),
            tab('Rechazadas', 0),
        ]);

        expect(shown.map((item) => item.label)).toEqual(['Todas']);
        expect(hidden.map((item) => item.label)).toEqual([
            'Pendientes',
            'Rechazadas',
        ]);
    });

    it('keeps the open tab even when it holds none, so the view never vanishes', () => {
        const { shown, hidden } = splitByAction([
            tab('Todas', 40),
            tab('Rechazadas', 0, { active: true }),
        ]);

        expect(shown.map((item) => item.label)).toEqual([
            'Todas',
            'Rechazadas',
        ]);
        expect(hidden).toEqual([]);
    });

    it('draws a tab without a count (a form, a document)', () => {
        expect(splitByAction([tab('Ajustes', undefined)]).shown).toHaveLength(
            1,
        );
    });
});
