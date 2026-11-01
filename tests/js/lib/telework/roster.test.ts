import { describe, expect, it } from 'vitest';
import {
    END_REASONS,
    expiryText,
    MARITAL_STATUSES,
    rosterHint,
} from '@/lib/telework/roster';

describe('expiryText', () => {
    it.each([
        ['valid', '2027-02-10', 'Vence el 10 de febrero de 2027'],
        ['valid_with_plan', '2027-02-10', 'Vence el 10 de febrero de 2027'],
        ['pending', '2026-02-10', 'Venció el 10 de febrero de 2026'],
        ['not_valid', null, null],
        ['pending', null, null],
        ['retired', '2027-02-10', null],
    ] as const)('says %s with %s: %s', (status, day, text) => {
        expect(expiryText(status, day)).toBe(text);
    });

    it('says nothing for a date it cannot read', () => {
        expect(expiryText('valid', 'soon')).toBeNull();
    });
});

describe('the option lists', () => {
    it('match the enums of the server', () => {
        expect(MARITAL_STATUSES.map((option) => option.value)).toEqual([
            'single',
            'married',
            'free_union',
            'separated',
            'divorced',
            'widowed',
        ]);
        expect(END_REASONS.map((reason) => reason.value)).toEqual([
            'reversibility',
            'left_company',
            'transfer',
            'other',
        ]);
    });
});

describe('rosterHint', () => {
    it('says since when a person is in the roster', () => {
        expect(rosterHint('active', false, '1 de marzo de 2026')).toBe(
            'Desde el 1 de marzo de 2026.',
        );
        expect(rosterHint('active', false, undefined)).toBe('En el padrón.');
    });

    it('tells an aspirant whether a workplace in force lets them join', () => {
        expect(rosterHint('aspirant', true, undefined)).toContain(
            'ya puedes pasarla',
        );
        expect(rosterHint('aspirant', false, undefined)).toContain(
            'al menos un lugar validado y vigente',
        );
    });
});
