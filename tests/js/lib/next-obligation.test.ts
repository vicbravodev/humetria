import { describe, expect, it } from 'vite-plus/test';
import { nextObligation } from '@/lib/app-navigation';

describe('nextObligation', () => {
    it('tells when the soonest evaluation closes', () => {
        expect(nextObligation({ count: 1, daysLeft: 11, elapsed: 21 })).toEqual(
            { lead: 'La próxima evaluación cierra', when: 'en 11 días' },
        );
        expect(
            nextObligation({ count: 2, daysLeft: 1, elapsed: 90 })?.when,
        ).toBe('mañana');
        expect(
            nextObligation({ count: 1, daysLeft: 0, elapsed: 100 })?.when,
        ).toBe('hoy');
    });

    it('has none without an evaluation in course', () => {
        expect(nextObligation({ count: 0, daysLeft: null, elapsed: 0 })).toBe(
            null,
        );
        expect(nextObligation(null)).toBe(null);
    });
});
