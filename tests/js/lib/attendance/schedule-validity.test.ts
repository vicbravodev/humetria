import { describe, expect, it } from 'vite-plus/test';

import {
    comingNote,
    sinceText,
    validityTimeline,
} from '@/lib/attendance/schedule-validity';
import type { WorkSchedule } from '@/types';

const schedule = (
    validFrom: string | null,
    shiftType = 'day',
): WorkSchedule => ({
    validFrom,
    shiftType: shiftType === 'night' ? 'night' : 'day',
    workMode: 'onsite',
    minutes: { 1: 480, 2: 480, 3: 480, 4: 480, 5: 480, 6: 0, 7: 0 },
    starts: null,
});

describe('when a schedule applies from', () => {
    it('says «desde siempre» for one with no start date, and the day for another', () => {
        expect(sinceText(null)).toBe('Desde siempre');
        expect(sinceText('2026-12-01')).toBe('Desde el 1 dic 2026');
    });

    it('says when the next change comes, or nothing', () => {
        expect(comingNote([], true)).toBeNull();
        expect(
            comingNote([schedule('2026-12-01'), schedule('2027-02-01')], true),
        ).toBe('Cambia el 1 dic 2026');
        expect(comingNote([schedule('2026-12-01')], false)).toBe(
            'Empieza el 1 dic 2026',
        );
    });

    it('lists what is in effect, what is coming and the past, each with its date, the coming ones first and the oldest last', () => {
        const timeline = validityTimeline(
            schedule('2026-11-01'),
            [schedule('2026-12-01'), schedule('2027-02-01')],
            [schedule('2026-03-02'), schedule(null)],
        );

        expect(timeline.map((entry) => [entry.kind, entry.since])).toEqual([
            ['coming', 'Desde el 1 feb 2027'],
            ['coming', 'Desde el 1 dic 2026'],
            ['current', 'Desde el 1 nov 2026'],
            ['past', 'Desde el 2 mar 2026'],
            ['past', 'Desde siempre'],
        ]);
    });

    it('lists nothing for a person with no schedule at all', () => {
        expect(validityTimeline(null, [], [])).toEqual([]);
    });
});
