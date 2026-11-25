import { describe, expect, it } from 'vite-plus/test';
import { dayTimer, spanText } from '@/lib/worker-access/day-timer';

const MIN = 60_000;
const START = Date.parse('2026-11-25T15:00:00Z');
const at = (minutes: number): number => START + minutes * MIN;

describe('spanText', () => {
    it.each([
        [0, '0 min'],
        [32, '32 min'],
        [59, '59 min'],
        [60, '1 h'],
        [221, '3 h 41 min'],
        [-5, '0 min'],
    ])('says %i minutes as «%s»', (minutes, text) => {
        expect(spanText(minutes)).toBe(text);
    });
});

describe('dayTimer', () => {
    it('counts nothing before the first mark', () => {
        expect(dayTimer([], at(10))).toBeNull();
    });

    it('counts the day since the start while they work', () => {
        expect(
            dayTimer([{ type: 'start', time: '09:00', at: at(0) }], at(221)),
        ).toEqual({ value: '3 h 41 min', label: 'de jornada' });
    });

    it('counts the break since it began', () => {
        expect(
            dayTimer(
                [
                    { type: 'start', time: '09:00', at: at(0) },
                    { type: 'break_start', time: '13:00', at: at(240) },
                ],
                at(272),
            ),
        ).toEqual({ value: '32 min', label: 'de comida' });
    });

    it('counts since the return from the break', () => {
        expect(
            dayTimer(
                [
                    { type: 'start', time: '09:00', at: at(0) },
                    { type: 'break_start', time: '13:00', at: at(240) },
                    { type: 'break_end', time: '13:30', at: at(270) },
                ],
                at(400),
            ),
        ).toEqual({ value: '2 h 10 min', label: 'desde su regreso' });
    });

    it('adds up what was worked, net of the break, once the day ended', () => {
        expect(
            dayTimer(
                [
                    { type: 'start', time: '09:00', at: at(0) },
                    { type: 'break_start', time: '13:00', at: at(240) },
                    { type: 'break_end', time: '13:30', at: at(270) },
                    { type: 'end', time: '18:00', at: at(540) },
                ],
                at(600),
            ),
        ).toEqual({ value: '8 h 30 min', label: 'trabajadas hoy' });
    });

    it('counts a day ended during the break only up to the break', () => {
        expect(
            dayTimer(
                [
                    { type: 'start', time: '09:00', at: at(0) },
                    { type: 'break_start', time: '13:00', at: at(240) },
                    { type: 'end', time: '13:10', at: at(250) },
                ],
                at(300),
            ),
        ).toEqual({ value: '4 h', label: 'trabajadas hoy' });
    });
});
