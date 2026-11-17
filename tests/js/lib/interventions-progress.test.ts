import { describe, expect, it } from 'vitest';
import {
    coverageText,
    requirementText,
    rowStateVariant,
    unfixedErrors,
} from '@/lib/interventions';

describe('rowStateVariant', () => {
    it('tells a row not started from one running, and one waiting for its follow-up from one done', () => {
        expect(rowStateVariant('none')).toBe('secondary');
        expect(rowStateVariant('planned')).toBe('outline');
        expect(rowStateVariant('in_progress')).toBe('default');
        expect(rowStateVariant('follow_up')).toBe('warning');
        expect(rowStateVariant('done')).toBe('success');
    });
});

describe('coverageText', () => {
    it('counts the themes with actions, in the singular and the plural', () => {
        expect(coverageText({ covered: 2, total: 15 })).toBe(
            '2 de 15 temas con acciones',
        );
        expect(coverageText({ covered: 0, total: 1 })).toBe(
            '0 de 1 tema con acciones',
        );
    });
});

describe('requirementText', () => {
    it('says what each line of the Tabla stands at: planned, running or done, never one «covered» for all', () => {
        const line = {
            key: 'analysis',
            text: 'Análisis',
            detail: null,
        };

        expect(requirementText({ ...line, status: 'planned' })).toBe(
            'Planeada',
        );
        expect(requirementText({ ...line, status: 'in_progress' })).toBe(
            'En curso',
        );
        expect(requirementText({ ...line, status: 'done' })).toBe('Hecha');
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
                status: 'done',
                detail: '3 de 3 filas',
            }),
        ).toBe('Hecha · 3 de 3 filas');
    });
});

describe('unfixedErrors', () => {
    it('drops the error of a field edited since the server answered, and keeps the rest', () => {
        const errors = {
            description: 'Falta',
            due_on: 'Falta',
            title: 'Falta',
        };

        expect(unfixedErrors(errors, ['description', 'due_on'])).toEqual({
            title: 'Falta',
        });
        expect(unfixedErrors(errors, [])).toEqual(errors);
    });
});
