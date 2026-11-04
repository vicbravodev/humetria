import { describe, expect, it } from 'vitest';
import {
    groupCode,
    normalizeCode,
    parseReceipt,
} from '@/lib/complaints/receipt';

describe('parseReceipt', () => {
    it('reads a reference and a key', () => {
        expect(
            parseReceipt({ reference: 'ABCD2345', key: 'K'.repeat(20) }),
        ).toEqual({
            reference: 'ABCD2345',
            key: 'K'.repeat(20),
        });
    });

    it.each([
        null,
        undefined,
        'x',
        3,
        {},
        { reference: 'A' },
        { key: 'A' },
        { reference: 1, key: 'A' },
        { reference: 'A', key: 2 },
    ])('reads nothing from %j', (value) => {
        expect(parseReceipt(value)).toBeUndefined();
    });
});

describe('groupCode and normalizeCode', () => {
    it('groups by four without a trailing space', () => {
        expect(groupCode('ABCD2345')).toBe('ABCD 2345');
        expect(groupCode('ABCDEFGHJKMNPQRSTVWX')).toBe(
            'ABCD EFGH JKMN PQRS TVWX',
        );
        expect(groupCode('ABC')).toBe('ABC');
        expect(groupCode('ABCD')).toBe('ABCD');
    });

    it('goes back to the plain code however it was typed', () => {
        expect(normalizeCode(' abcd-2345 ')).toBe('ABCD2345');
        expect(normalizeCode(groupCode('ABCD2345'))).toBe('ABCD2345');
    });
});
