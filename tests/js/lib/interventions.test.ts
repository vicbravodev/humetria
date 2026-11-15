import { describe, expect, it } from 'vitest';
import {
    activeTab,
    meterText,
    programStartText,
    programTools,
    byRisk,
    programTrail,
    themeCoverage,
    requirementText,
    SCOPE_LABELS,
    stateVariant,
    statusVariant,
    subjectsText,
    tabHref,
} from '@/lib/interventions';
import { index } from '@/routes/tenant/campaigns';
import { route } from '@/routes/tenant/nom035';
import type { ProgramAction, ProgramPageProps } from '@/types';

const action = (overrides: Partial<ProgramAction> = {}): ProgramAction => ({
    id: 'a1',
    itemId: 'i1',
    itemLabel: 'Calificación final',
    level: { value: 1, label: 'Primer nivel: organizacional' },
    kind: { value: 'policy_review', label: 'Revisión de la política' },
    title: 'Revisar',
    description: 'Revisar la política',
    subjects: { value: 'center', label: 'Todo el centro de trabajo' },
    areas: [],
    hiddenAreas: 0,
    startsOn: null,
    startsOnLabel: null,
    dueOn: '2026-12-15',
    dueOnLabel: '15 dic 2026',
    responsible: { name: 'Laura', userId: null },
    professional: null,
    status: { value: 'planned', label: 'Planeada' },
    overdue: false,
    history: [],
    completedOnLabel: null,
    ...overrides,
});

describe('programStartText', () => {
    it('says the results ask for a Programa', () => {
        expect(programStartText('required').title).toBe(
            'Esta evaluación pide un Programa de intervención',
        );
    });

    it('explains why a sample under the minimum gets no rows', () => {
        expect(programStartText('below_minimum').body).toContain(
            'ninguna fila',
        );
    });

    it('says the norm asks for none when nobody is in risk', () => {
        expect(programStartText('not_required').body).toContain('num. 8.3');
    });
});

describe('SCOPE_LABELS', () => {
    it('names what a row attends', () => {
        expect(SCOPE_LABELS).toEqual({
            final: 'Calificación final',
            category: 'Categoría',
            domain: 'Dominio',
        });
    });
});

describe('programTrail', () => {
    // Rediseño v2 (board V2-N035-Plan): the Programa is entered from stage 5 of the route of its center, and leads back to it.
    it('goes from NOM-035 through the route of the center to the Programa', () => {
        expect(
            programTrail('acme', {
                id: 'c1',
                workCenterId: 'w1',
                workCenter: 'Planta Norte',
                kindLabel: 'Evaluación NOM-035',
                closedOn: '19 nov 2026',
                guide: 'Guía II',
            }),
        ).toEqual([
            { title: 'NOM-035', href: index('acme') },
            { title: 'Planta Norte', href: route(['acme', 'w1']) },
            { title: 'Programa de intervención' },
        ]);
    });
});

describe('tabs', () => {
    it('takes the tab from the address and opens the rows without one', () => {
        expect(activeTab('?tab=actions')).toBe('actions');
        expect(activeTab('?tab=follow-up&page=2')).toBe('follow-up');
        expect(activeTab('')).toBe('rows');
        expect(activeTab('?tab=nonsense')).toBe('rows');
    });

    it('writes the address of each tab, the rows being the page itself', () => {
        expect(tabHref('/admin/acme/p', 'rows')).toBe('/admin/acme/p');
        expect(tabHref('/admin/acme/p', 'actions')).toBe(
            '/admin/acme/p?tab=actions',
        );
    });
});

describe('variants', () => {
    it('gives each state of an action its look', () => {
        expect(statusVariant('planned')).toBe('outline');
        expect(statusVariant('in_progress')).toBe('default');
        expect(statusVariant('done')).toBe('success');
        expect(statusVariant('cancelled')).toBe('secondary');
    });

    it('gives each state of a Programa its look', () => {
        expect(stateVariant('none')).toBe('secondary');
        expect(stateVariant('in_progress')).toBe('default');
        expect(stateVariant('overdue')).toBe('destructive');
        expect(stateVariant('done')).toBe('success');
    });
});

describe('meterText', () => {
    it('counts the actions done, in the singular and the plural', () => {
        expect(meterText({ done: 3, total: 8 })).toBe('3 de 8 acciones hechas');
        expect(meterText({ done: 0, total: 1 })).toBe('0 de 1 acción hecha');
    });

    it('says there are no actions yet', () => {
        expect(meterText({ done: 0, total: 0 })).toBe('Aún no hay acciones');
    });
});

describe('subjectsText', () => {
    it('says the whole center', () => {
        expect(subjectsText(action())).toBe('Todo el centro de trabajo');
    });

    it('lists the areas shown, and how many more are hidden', () => {
        const areas = [
            { id: 'a', name: 'Producción' },
            { id: 'b', name: 'Ventas' },
        ];
        const areaAction = action({
            subjects: { value: 'areas', label: 'Áreas' },
            areas,
        });

        expect(subjectsText(areaAction)).toBe('Producción, Ventas');
        expect(subjectsText({ ...areaAction, hiddenAreas: 2 })).toBe(
            'Producción, Ventas y 2 más',
        );
    });
});

describe('requirementText', () => {
    it('says what each line of the Tabla stands at', () => {
        const line = {
            key: 'analysis',
            text: 'Análisis',
            detail: null,
        };

        expect(requirementText({ ...line, status: 'covered' })).toBe(
            'Cubierto',
        );
        expect(requirementText({ ...line, status: 'optional' })).toBe(
            'Opcional',
        );
        expect(requirementText({ ...line, status: 'missing' })).toBe('Falta');
        expect(
            requirementText({
                ...line,
                status: 'missing',
                detail: '2 de 3 filas',
            }),
        ).toBe('Falta · 2 de 3 filas');
        expect(
            requirementText({
                ...line,
                status: 'covered',
                detail: '3 de 3 filas',
            }),
        ).toBe('Cubierto · 3 de 3 filas');
    });
});

describe('programTools', () => {
    const page = (overrides: Partial<ProgramPageProps>): ProgramPageProps => ({
        campaign: {
            id: 'c1',
            workCenterId: 'w1',
            workCenter: 'Planta',
            kindLabel: 'Evaluación NOM-035',
            closedOn: '19 nov 2026',
            guide: 'Guía II',
        },
        program: null,
        requirement: null,
        actions: null,
        filters: { item: null, status: null },
        catalog: { kinds: [], levels: [], professionals: [] },
        manualOptions: [],
        followUpEvaluations: [{ id: 'e1', label: 'Cerrada el 1 nov 2028' }],
        followUpSuggestion: { date: '2028-11-19', label: '19 nov 2028' },
        areaOptions: [],
        members: [],
        today: '2026-11-20',
        can: { manage: true },
        ...overrides,
    });

    it('gives whoever writes the Programa the choices of every form', () => {
        expect(programTools(page({}))).toEqual({
            catalog: { kinds: [], levels: [], professionals: [] },
            areas: [],
            members: [],
            manualOptions: [],
            evaluations: [{ id: 'e1', label: 'Cerrada el 1 nov 2028' }],
            suggestion: '2028-11-19',
            today: '2026-11-20',
            responsible: null,
        });
    });

    it('gives whoever only reads it nothing, the server sending no tools', () => {
        expect(
            programTools(page({ catalog: null, can: { manage: false } })),
        ).toBeNull();
        expect(programTools(page({ members: null }))).toBeNull();
        expect(programTools(page({ areaOptions: null }))).toBeNull();
        expect(programTools(page({ manualOptions: null }))).toBeNull();
    });

    it('has no suggestion and no evaluations when the server sends none', () => {
        expect(
            programTools(
                page({ followUpEvaluations: null, followUpSuggestion: null }),
            ),
        ).toMatchObject({ evaluations: [], suggestion: undefined });
    });
});

describe('themeCoverage', () => {
    it('counts the themes with actions and the actions, as the board heads the Programa', () => {
        const row = (total: number) => ({ meter: { done: 0, total } });

        expect(themeCoverage([row(1), row(0), row(2)])).toEqual({
            covered: 2,
            total: 3,
            actions: 3,
            text: '2 de 3 temas con acciones',
        });
        expect(themeCoverage([row(1)]).text).toBe('1 de 1 tema con acciones');
    });
});

describe('byRisk', () => {
    it('puts first the rows with the highest level, the ones RH added last', () => {
        const row = (
            id: string,
            value: 'medium' | 'high' | 'very_high' | null,
        ) => ({
            id,
            level: value === null ? null : { value, label: value },
        });

        expect(
            byRisk([
                row('a', 'medium'),
                row('b', null),
                row('c', 'very_high'),
                row('d', 'high'),
            ]).map((each) => each.id),
        ).toEqual(['c', 'd', 'a', 'b']);
    });
});
