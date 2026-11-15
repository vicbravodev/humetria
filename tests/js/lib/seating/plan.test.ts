import { describe, expect, it } from 'vite-plus/test';

import { measureColumns, ruleLine } from '@/lib/seating/plan';
import type { MeasureRow } from '@/types';

function measure(id: string, status: MeasureRow['status']): MeasureRow {
    return {
        id,
        kindLabel: 'Programa de pausas activas',
        note: null,
        status,
        statusLabel: status,
        positionName: 'Operador de línea',
        ownerName: 'Olga Owner',
        dueOn: '2026-11-13',
        overdue: false,
        hasEvidence: false,
        fromAssessment: false,
        evidenceHref: null,
    };
}

describe('measureColumns', () => {
    it('lays the measures of the page in planned, in progress and done, and counts the cancelled ones apart', () => {
        const { columns, cancelled } = measureColumns([
            measure('a', 'planned'),
            measure('b', 'done'),
            measure('c', 'cancelled'),
            measure('d', 'planned'),
            measure('e', 'in_progress'),
        ]);

        expect(columns.map((column) => column.title)).toEqual([
            'Planeada',
            'En curso',
            'Hecha',
        ]);
        expect(
            columns.map((column) => column.measures.map((each) => each.id)),
        ).toEqual([['a', 'd'], ['e'], ['b']]);
        expect(cancelled).toBe(1);
    });
});

describe('ruleLine', () => {
    it('says whether every medium or high assessment has its measure', () => {
        expect(ruleLine({ state: 'met', count: 0 })).toEqual({
            met: true,
            text: 'cada evaluación de nivel medio o alto tiene una medida.',
        });
        expect(ruleLine({ state: 'missing', count: 1 })).toEqual({
            met: false,
            text: '1 evaluación de nivel medio o alto no tiene medida.',
        });
        expect(ruleLine({ state: 'missing', count: 3 }).text).toBe(
            '3 evaluaciones de nivel medio o alto no tienen medida.',
        );
        expect(ruleLine({ state: 'pending', count: 0 })).toEqual({
            met: false,
            text: 'aún no hay evaluaciones firmadas.',
        });
        expect(ruleLine({ state: 'warning', count: 2 })).toEqual({
            met: false,
            text: '2 medidas pasaron su fecha compromiso.',
        });
    });
});
