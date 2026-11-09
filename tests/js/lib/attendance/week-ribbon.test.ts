import { describe, expect, it } from 'vitest';
import {
    RIBBON_CAP_MINUTES,
    RIBBON_INITIALS,
    ribbonBars,
    ribbonLabel,
} from '@/lib/attendance/week-ribbon';

const hours = (h: number): number => h * 60;
const none = { worked: 0, overtime: 0 };

describe('ribbonBars', () => {
    it('always draws seven bars, Monday to Sunday, empty where nothing was worked', () => {
        const bars = ribbonBars([{ worked: hours(8), overtime: 0 }]);

        expect(bars).toHaveLength(7);
        expect(bars[0]).toMatchObject({ empty: false });
        expect(bars[6]).toEqual({
            empty: true,
            ordinary: 0,
            overtime: 0,
            over: false,
        });
    });

    it('caps the bar at 12 h, so 6 h fills half of it', () => {
        expect(RIBBON_CAP_MINUTES).toBe(hours(12));
        expect(ribbonBars([{ worked: hours(6), overtime: 0 }])[0]).toEqual({
            empty: false,
            ordinary: 50,
            overtime: 0,
            over: false,
        });
    });

    it('stacks the overtime on the ordinary hours', () => {
        expect(
            ribbonBars([{ worked: hours(9), overtime: hours(3) }])[0],
        ).toEqual({ empty: false, ordinary: 50, overtime: 25, over: false });
    });

    it('marks a day of more than 12 h, and never grows past the cap', () => {
        const bar = ribbonBars([{ worked: hours(13), overtime: hours(5) }])[0];

        expect(bar?.over).toBe(true);
        expect(bar?.ordinary).toBeCloseTo((8 / 12) * 100);
        expect(bar?.overtime).toBeCloseTo((4 / 12) * 100);
    });

    it('does not mark exactly 12 h', () => {
        expect(
            ribbonBars([{ worked: hours(12), overtime: 0 }])[0],
        ).toMatchObject({ ordinary: 100, over: false });
    });

    it('ignores days past the seventh', () => {
        expect(ribbonBars(Array.from({ length: 9 }, () => none))).toHaveLength(
            7,
        );
    });
});

describe('ribbonLabel', () => {
    it('reads each day for a screen reader, naming the days with no hours', () => {
        const days = [
            { worked: hours(8), overtime: 0 },
            { worked: hours(9) + 30, overtime: 90 },
        ];

        expect(ribbonLabel(days)).toBe(
            'Horas por día: lunes 8 h, martes 9 h 30 min, miércoles sin horas, jueves sin horas, viernes sin horas, sábado sin horas, domingo sin horas',
        );
    });
});

describe('RIBBON_INITIALS', () => {
    it('names the seven bars by the initial of their day, Monday first, to go under them', () => {
        expect(RIBBON_INITIALS).toEqual(['L', 'M', 'M', 'J', 'V', 'S', 'D']);
    });
});
