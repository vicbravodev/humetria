import { describe, expect, it } from 'vite-plus/test';
import {
    monthsLimit,
    peopleHint,
    raiseSuffix,
} from '@/lib/compensation/raise-draft';

describe('raise draft', () => {
    it('offers up to 36 months of retroactivity, fewer only for a shorter horizon', () => {
        expect(monthsLimit('3')).toBe(36);
        expect(monthsLimit('5')).toBe(36);
        expect(monthsLimit('2')).toBe(24);
        expect(monthsLimit('')).toBe(36);
    });

    it('writes what the raise is in and how many people it reaches', () => {
        expect(raiseSuffix('percent')).toBe('%');
        expect(raiseSuffix('amount')).toBe('al mes');
        expect(peopleHint(undefined)).toBeUndefined();
        expect(peopleHint(1)).toBe('1 persona');
        expect(peopleHint(22, ' · ')).toBe('22 personas · ');
    });
});
