import { describe, expect, it } from 'vitest';
import { entryDays, owedDays } from '@/lib/leave/record';

describe('entryDays', () => {
    it('signs the days that enter and leave', () => {
        expect(entryDays(3)).toBe('+3 días');
        expect(entryDays(1)).toBe('+1 día');
        expect(entryDays(-1)).toBe('−1 día');
        expect(entryDays(-12)).toBe('−12 días');
    });
});

describe('owedDays', () => {
    it('adds the pending and the proportional days to two decimals', () => {
        expect(owedDays(16, 5.98)).toBe(21.98);
        expect(owedDays(0.1, 0.2)).toBe(0.3);
        expect(owedDays(-2, 5)).toBe(3);
    });
});
