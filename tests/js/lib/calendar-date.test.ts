import { describe, expect, it } from 'vite-plus/test';

import {
    describeDate,
    fromCalendarDate,
    toCalendarDate,
} from '@/lib/calendar-date';

describe('calendar dates', () => {
    it('reads a Y-m-d date as that day at local midnight', () => {
        const date = fromCalendarDate('2026-11-30');

        expect(date?.getFullYear()).toBe(2026);
        expect(date?.getMonth()).toBe(10);
        expect(date?.getDate()).toBe(30);
        expect(date?.getHours()).toBe(0);
    });

    it.each(['', '30/11/2026', '2026-13-01', '2026-02-30', '2026-1-5'])(
        'reads %j as no date',
        (text) => {
            expect(fromCalendarDate(text)).toBeUndefined();
        },
    );

    it('writes a day back as Y-m-d, padding month and day', () => {
        expect(toCalendarDate(new Date(2027, 0, 5, 23, 59))).toBe('2027-01-05');
    });

    it('describes a day in Mexican Spanish', () => {
        expect(describeDate('2026-11-30')).toBe('30 de noviembre de 2026');
        expect(describeDate('nada')).toBeUndefined();
    });
});
