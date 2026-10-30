import { describe, expect, it } from 'vitest';
import { formatCountdown } from '@/lib/countdown';

describe('formatCountdown', () => {
    it('writes the seconds left as minutes and two-digit seconds', () => {
        expect(formatCountdown(42)).toBe('0:42');
        expect(formatCountdown(60)).toBe('1:00');
        expect(formatCountdown(65)).toBe('1:05');
    });

    it('never goes below zero', () => {
        expect(formatCountdown(0)).toBe('0:00');
        expect(formatCountdown(-3)).toBe('0:00');
    });
});
