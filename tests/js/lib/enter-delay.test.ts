import { describe, expect, it } from 'vitest';
import { enterDelay } from '@/lib/enter-delay';

describe('enterDelay', () => {
    it('names the class that holds an entrance back by the seconds given, in milliseconds', () => {
        expect(enterDelay(0)).toBe('enter-delay-0');
        expect(enterDelay(0.2)).toBe('enter-delay-200');
        expect(enterDelay(1)).toBe('enter-delay-1000');
    });

    it('rounds the floating-point noise of a stagger to a whole 10 ms', () => {
        // 0.1 + 2 * 0.08 is 0.26000000000000001 and 3 * 0.07 is 0.21000000000000002: both are meant as 260 and 210.
        expect(enterDelay(0.1 + 2 * 0.08)).toBe('enter-delay-260');
        expect(enterDelay(3 * 0.07)).toBe('enter-delay-210');
        expect(enterDelay(0.55)).toBe('enter-delay-550');
    });
});
