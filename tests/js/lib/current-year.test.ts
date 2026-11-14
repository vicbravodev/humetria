import { afterEach, describe, expect, it, vi } from 'vitest';
import { currentYear } from '@/lib/current-year';

describe('currentYear', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('reads the clock each time it is called, not once when the module loads', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-12-31T12:00:00Z'));
        expect(currentYear()).toBe(2026);

        vi.setSystemTime(new Date('2027-06-01T12:00:00Z'));
        expect(currentYear()).toBe(2027);
    });
});
