import { describe, expect, it } from 'vitest';
import { ANY_STEP, markName, nextSteps } from '@/lib/attendance/next-step';

describe('nextSteps', () => {
    it.each([
        { next: ['start'], type: 'start', label: 'Marcar entrada', others: [] },
        {
            next: ['break_start', 'end'],
            type: 'break_start',
            label: 'Salir a comer',
            others: ['end'],
        },
        {
            next: ['break_end', 'end'],
            type: 'break_end',
            label: 'Regresar de comer',
            others: ['end'],
        },
    ])(
        'offers $next with the first as the big button',
        ({ next, type, label, others }) => {
            const steps = nextSteps(next);

            expect(steps.primary).toEqual({ type, label });
            expect(steps.others.map((step) => step.type)).toEqual(others);
        },
    );

    it('names the end of the day', () => {
        expect(nextSteps(['end']).primary).toEqual({
            type: 'end',
            label: 'Marcar salida',
        });
    });

    it('leaves out what is not a mark, and has no button when nothing is left', () => {
        expect(nextSteps(['lunch', 'start'])).toEqual({
            primary: { type: 'start', label: 'Marcar entrada' },
            others: [],
        });
        expect(nextSteps([])).toEqual({ primary: null, others: [] });
    });
});

describe('markName', () => {
    it.each([
        ['start', 'Entrada'],
        ['break_start', 'Salida a comer'],
        ['break_end', 'Regreso de comer'],
        ['end', 'Salida'],
        ['lunch', 'Marca'],
    ])('names %s in the day as %s', (type, name) => {
        expect(markName(type)).toBe(name);
    });
});

describe('ANY_STEP', () => {
    it('offers every step, the start first, to someone not known yet', () => {
        expect(nextSteps(ANY_STEP).primary?.type).toBe('start');
        expect(nextSteps(ANY_STEP).others.map((step) => step.type)).toEqual([
            'break_start',
            'break_end',
            'end',
        ]);
    });
});
