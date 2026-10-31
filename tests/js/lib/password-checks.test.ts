import { describe, expect, it } from 'vitest';
import { filledSegments, passwordChecks } from '@/lib/password-checks';

const PRODUCTION =
    'minlength: 12; required: lower; required: upper; required: digit; required: special;';

describe('passwordChecks', () => {
    it('lists what the server asks for, from its passwordrules', () => {
        expect(
            passwordChecks(PRODUCTION, '').map((check) => check.label),
        ).toEqual([
            'Al menos 12 caracteres',
            'Mayúsculas y minúsculas',
            'Al menos un número',
            'Al menos un símbolo',
        ]);
    });

    it('marks each one the password already meets', () => {
        expect(
            passwordChecks(PRODUCTION, 'abcdefghijK1').map(
                (check) => check.met,
            ),
        ).toEqual([true, true, true, false]);
        expect(
            passwordChecks(PRODUCTION, 'Ab1!').map((check) => check.met),
        ).toEqual([false, true, true, true]);
    });

    it('counts the length exactly at the minimum', () => {
        expect(passwordChecks('minlength: 8;', '1234567')).toEqual([
            { label: 'Al menos 8 caracteres', met: false },
        ]);
        expect(passwordChecks('minlength: 8;', '12345678')).toEqual([
            { label: 'Al menos 8 caracteres', met: true },
        ]);
    });

    it('asks for any letter when lowercase alone is required', () => {
        expect(passwordChecks('required: lower;', 'Ñ')).toEqual([
            { label: 'Al menos una letra', met: true },
        ]);
        expect(passwordChecks('required: lower;', '123')).toEqual([
            { label: 'Al menos una letra', met: false },
        ]);
    });

    it('needs both cases when mixed case is required', () => {
        expect(
            passwordChecks('required: lower; required: upper;', 'abc')[0],
        ).toEqual({ label: 'Mayúsculas y minúsculas', met: false });
        expect(
            passwordChecks('required: lower; required: upper;', 'ABC')[0],
        ).toEqual({ label: 'Mayúsculas y minúsculas', met: false });
    });

    it('is empty without rules', () => {
        expect(passwordChecks('', 'x')).toEqual([]);
    });
});

describe('filledSegments', () => {
    it('fills the meter in proportion to the checks met', () => {
        expect(filledSegments(passwordChecks(PRODUCTION, ''))).toBe(0);
        expect(filledSegments(passwordChecks(PRODUCTION, 'abcdefghijK1'))).toBe(
            3,
        );
        expect(
            filledSegments(passwordChecks(PRODUCTION, 'abcdefghijK1!')),
        ).toBe(4);
        expect(
            filledSegments(passwordChecks('minlength: 8;', '12345678')),
        ).toBe(4);
    });

    it('is empty without checks', () => {
        expect(filledSegments([])).toBe(0);
    });
});
