import { describe, expect, it } from 'vite-plus/test';

import { passwordChecks } from '@/lib/password-rules';

const PRODUCTION =
    'minlength: 12; required: lower; required: upper; required: digit; required: special;';

describe('passwordChecks', () => {
    it('lists what the server asks for, in its order', () => {
        expect(
            passwordChecks(PRODUCTION, '').map(({ label }) => label),
        ).toEqual([
            'Al menos 12 caracteres',
            'Una minúscula',
            'Una mayúscula',
            'Un número',
            'Un símbolo',
        ]);
    });

    it('marks what a password already meets', () => {
        expect(
            passwordChecks(PRODUCTION, 'ñandú1234567').map(({ met }) => met),
        ).toEqual([true, true, false, true, false]);
        expect(
            passwordChecks(PRODUCTION, 'Ñandú-123456').every(({ met }) => met),
        ).toBe(true);
    });

    it('counts characters, not bytes, and stops one short of the minimum', () => {
        expect(passwordChecks('minlength: 3;', 'ñú')[0]?.met).toBe(false);
        expect(passwordChecks('minlength: 3;', 'ñúé')[0]?.met).toBe(true);
    });

    it('asks only for a letter when case does not matter', () => {
        expect(passwordChecks('minlength: 8; required: lower;', 'A')).toEqual([
            { label: 'Al menos 8 caracteres', met: false },
            { label: 'Una letra', met: true },
        ]);
    });

    it('ignores what it does not know', () => {
        expect(passwordChecks('maxlength: 64; allowed: ascii;', 'x')).toEqual(
            [],
        );
    });
});
