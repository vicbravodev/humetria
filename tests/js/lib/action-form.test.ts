import { describe, expect, it } from 'vitest';
import {
    actionDefaults,
    areaChoices,
    atRiskText,
    isOpen,
    levelAfterKind,
    levelOfKind,
    needsProfessional,
    nextStatuses,
    statusChoices,
} from '@/lib/action-form';
import type { ActionCatalog, ProgramAction } from '@/types';

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

describe('atRiskText', () => {
    it('counts the questionnaires of an area in Medio or more', () => {
        expect(atRiskText(0)).toBe('Ninguno en riesgo medio o más');
        expect(atRiskText(1)).toBe('1 cuestionario en riesgo medio o más');
        expect(atRiskText(7)).toBe('7 cuestionarios en riesgo medio o más');
    });

    it('describes each area for a row', () => {
        expect(
            areaChoices(
                [
                    {
                        id: 'a',
                        name: 'Producción',
                        answered: 7,
                        atRisk: { i1: 3 },
                    },
                ],
                'i1',
            ),
        ).toEqual([
            {
                id: 'a',
                name: 'Producción',
                hint: '3 cuestionarios en riesgo medio o más',
            },
        ]);
        expect(
            areaChoices(
                [{ id: 'a', name: 'Producción', answered: 7, atRisk: {} }],
                'i2',
            )[0]?.hint,
        ).toBe('Ninguno en riesgo medio o más');
    });
});

describe('kinds and levels', () => {
    const catalog: ActionCatalog = {
        kinds: [
            { value: 'policy_review', label: 'Revisión', level: 1 },
            { value: 'clinical_care', label: 'Atención', level: 3 },
            { value: 'other', label: 'Otra', level: null },
        ],
        levels: [],
        professionals: [],
    };

    it('gives the level a kind belongs to, and none for the one without', () => {
        expect(levelOfKind(catalog, 'clinical_care')).toBe(3);
        expect(levelOfKind(catalog, 'other')).toBeNull();
        expect(levelOfKind(catalog, 'unknown')).toBeNull();
    });

    it('asks for the professional on the third level only', () => {
        expect(needsProfessional(3)).toBe(true);
        expect(needsProfessional(2)).toBe(false);
    });
});

describe('nextStatuses', () => {
    it('lets a planned action start or finish, and a running one go on or finish', () => {
        expect(nextStatuses('planned').map((step) => step.value)).toEqual([
            'in_progress',
            'done',
        ]);
        expect(nextStatuses('in_progress').map((step) => step.value)).toEqual([
            'in_progress',
            'done',
        ]);
    });

    it('lets a done action only be reopened, and a cancelled one nothing', () => {
        expect(nextStatuses('done')).toEqual([
            { value: 'in_progress', label: 'Reabrir (en curso)' },
        ]);
        expect(nextStatuses('cancelled')).toEqual([]);
    });

    it('knows which actions take steps', () => {
        expect(isOpen('planned')).toBe(true);
        expect(isOpen('done')).toBe(true);
        expect(isOpen('cancelled')).toBe(false);
    });
});

describe('the action form', () => {
    const catalog: ActionCatalog = {
        kinds: [
            { value: 'policy_review', label: 'Revisión', level: 1 },
            { value: 'clinical_care', label: 'Atención', level: 3 },
            { value: 'other', label: 'Otra', level: null },
        ],
        levels: [],
        professionals: [],
    };

    it('starts a new action on the first kind, at its level', () => {
        expect(actionDefaults(catalog, null)).toEqual({
            kind: 'policy_review',
            level: 1,
            professional: 'psychologist',
            subjects: 'center',
            title: '',
            description: '',
            startsOn: undefined,
            dueOn: undefined,
            userId: null,
            name: '',
            areaIds: [],
        });
    });

    it('starts an edit on what the action has', () => {
        const edited = action({
            title: 'Revisar',
            startsOn: '2026-12-01',
            responsible: { name: 'Hugo', userId: 7 },
            subjects: { value: 'areas', label: 'Áreas' },
            areas: [{ id: 'a', name: 'Producción' }],
        });

        expect(actionDefaults(catalog, edited)).toMatchObject({
            title: 'Revisar',
            startsOn: '2026-12-01',
            dueOn: '2026-12-15',
            userId: 7,
            name: 'Hugo',
            subjects: 'areas',
            areaIds: ['a'],
        });
    });

    it('follows the kind to its level, and leaves the clinical third when the kind is not clinical', () => {
        expect(levelAfterKind(catalog, 'clinical_care', 1)).toBe(3);
        expect(levelAfterKind(catalog, 'policy_review', 2)).toBe(1);
        expect(levelAfterKind(catalog, 'other', 2)).toBe(2);
        expect(levelAfterKind(catalog, 'other', 3)).toBe(1);
    });
});

describe('statusChoices', () => {
    it('lists the states in the order of an action life', () => {
        expect(statusChoices().map((status) => status.label)).toEqual([
            'Planeada',
            'En curso',
            'Hecha',
            'Cancelada',
        ]);
    });
});
