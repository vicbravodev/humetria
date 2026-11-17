import { describe, expect, it } from 'vitest';
import {
    firstStepNotice,
    markName,
    nextSteps,
    stepThatProceeds,
} from '@/lib/attendance/next-step';

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

describe('stepThatProceeds', () => {
    it('is the step tapped when it proceeds, or else the first one the server offers: no signature is spent on one that does not (hallazgo 27)', () => {
        expect(stepThatProceeds('break_start', ['break_start', 'end'])).toBe(
            'break_start',
        );
        expect(stepThatProceeds('break_start', ['start'])).toBe('start');
        expect(stepThatProceeds('end', ['x', 'break_end'])).toBe('break_end');
    });

    it('is the step tapped when the server offered none it knows', () => {
        expect(stepThatProceeds('end', [])).toBe('end');
    });
});

describe('firstStepNotice', () => {
    it('says which step goes first', () => {
        expect(firstStepNotice(['start'])).toBe('Primero marque su entrada.');
        expect(firstStepNotice(['break_end', 'end'])).toBe(
            'Primero marque su regreso de comer.',
        );
        expect(firstStepNotice([])).toBe('Ese paso no corresponde ahora.');
    });
});
