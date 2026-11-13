import { describe, expect, it } from 'vite-plus/test';
import { inheritedResponsible, programTools } from '@/lib/interventions';
import type { ProgramPageProps } from '@/types';

const page = (responsible: string | null): ProgramPageProps => ({
    campaign: {
        id: 'c1',
        workCenterId: 'w1',
        workCenter: 'Planta',
        kindLabel: 'Evaluación NOM-035',
        closedOn: '19 nov 2026',
        guide: 'Guía II',
    },
    program: {
        id: 'p1',
        highestLevel: null,
        responsible:
            responsible === null ? null : { name: responsible, userId: null },
        state: { value: 'none', label: 'Sin acciones' },
        meter: { done: 0, total: 0 },
        overdue: 0,
        requirements: [],
        items: [],
    },
    requirement: null,
    actions: null,
    filters: { item: null, status: null },
    catalog: { kinds: [], levels: [], professionals: [] },
    manualOptions: [],
    followUpEvaluations: [],
    followUpSuggestion: null,
    areaOptions: [],
    members: [],
    today: '2026-11-20',
    can: { manage: true },
});

describe('the responsible an action inherits', () => {
    it('carries the name of the responsible of the Programa in the tools', () => {
        expect(programTools(page('Ing. Carlos Ruiz'))?.responsible).toBe(
            'Ing. Carlos Ruiz',
        );
        expect(programTools(page(null))?.responsible).toBeNull();
    });

    it('tells who an action falls to when its responsible is left empty', () => {
        expect(inheritedResponsible('Ing. Carlos Ruiz')).toBe(
            'Si lo dejas vacío: Ing. Carlos Ruiz',
        );
    });

    it('has nothing to say when the Programa has no responsible', () => {
        expect(inheritedResponsible(null)).toBeUndefined();
    });
});
