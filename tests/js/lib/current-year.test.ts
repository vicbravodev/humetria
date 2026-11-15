import { afterEach, describe, expect, it, vi } from 'vitest';
import { currentYear } from '@/lib/current-year';

describe('currentYear', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('reads the clock each time it is called, not once when the module loads', () => {
        // Local noon, so the year on the clock is the same in every time zone the machine can be in.
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 11, 31, 12));
        expect(currentYear()).toBe(2026);

        vi.setSystemTime(new Date(2027, 5, 1, 12));
        expect(currentYear()).toBe(2027);
    });
});
