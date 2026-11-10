import { describe, expect, it } from 'vitest';
import { dateSpan, statusTone, workingDaysOf } from '@/lib/leave/format';
import type { LeaveRequestStatus } from '@/types';

describe('dateSpan', () => {
    it.each([
        ['2026-11-09', '2026-11-09', '9 de noviembre de 2026'],
        ['2026-11-09', '2026-11-20', '9 al 20 de noviembre de 2026'],
        [
            '2026-11-30',
            '2026-12-04',
            '30 de noviembre al 4 de diciembre de 2026',
        ],
        [
            '2026-12-28',
            '2027-01-08',
            '28 de diciembre de 2026 al 8 de enero de 2027',
        ],
    ])('writes %s to %s as «%s»', (startsOn, endsOn, text) => {
        expect(dateSpan(startsOn, endsOn)).toBe(text);
    });

    it('writes nothing for what is not a date', () => {
        expect(dateSpan('', '2026-11-20')).toBe('');
        expect(dateSpan('2026-11-09', 'mañana')).toBe('');
    });
});

describe('workingDaysOf', () => {
    it('counts with the noun in its number', () => {
        expect(workingDaysOf(1)).toBe('1 día laborable');
        expect(workingDaysOf(9)).toBe('9 días laborables');
    });
});

describe('statusTone', () => {
    it.each<[LeaveRequestStatus, string]>([
        ['requested', 'default'],
        ['approved', 'success'],
        ['rejected', 'destructive'],
        ['cancelled', 'secondary'],
        ['taken', 'secondary'],
    ])('paints %s as %s', (status, tone) => {
        expect(statusTone(status)).toBe(tone);
    });
});
