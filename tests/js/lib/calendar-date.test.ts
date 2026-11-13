import { describe, expect, it } from 'vite-plus/test';

import {
    describeDate,
    shortDate,
    fromCalendarDate,
    calendarLimits,
    pickableDays,
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

    it('writes a day short for a table or a line, and leaves anything else as it came', () => {
        expect(shortDate('2026-11-25')).toBe('25 nov 2026');
        expect(shortDate('2026-02-30')).toBe('2026-02-30');
        expect(shortDate('pronto')).toBe('pronto');
    });
});

describe('pickableDays', () => {
    it('allows every day when there are no limits', () => {
        expect(pickableDays(undefined, undefined)).toBeUndefined();
    });

    it('closes the days before the first and after the last', () => {
        const days = pickableDays('2026-11-10', '2026-11-20');

        expect(days).toHaveLength(2);
        expect(days).toEqual([
            { before: new Date('2026-11-10T00:00') },
            { after: new Date('2026-11-20T00:00') },
        ]);
    });

    it('closes only the side that has a limit', () => {
        expect(pickableDays('2026-11-10', undefined)).toEqual([
            { before: new Date('2026-11-10T00:00') },
        ]);
        expect(pickableDays(undefined, '2026-11-20')).toEqual([
            { after: new Date('2026-11-20T00:00') },
        ]);
    });

    it('ignores a limit that is not a date', () => {
        expect(pickableDays('mañana', '2026-02-30')).toBeUndefined();
    });
});

describe('calendarLimits', () => {
    it('limits nothing without a first or a last day', () => {
        expect(calendarLimits(undefined, undefined)).toEqual({});
    });

    it('starts the calendar at the first day and closes what is out of range', () => {
        expect(calendarLimits('2026-11-10', '2026-11-20')).toEqual({
            startMonth: new Date('2026-11-10T00:00'),
            disabled: [
                { before: new Date('2026-11-10T00:00') },
                { after: new Date('2026-11-20T00:00') },
            ],
        });
    });

    it('only closes the future when there is a last day and no first', () => {
        expect(calendarLimits(undefined, '2026-11-20')).toEqual({
            disabled: [{ after: new Date('2026-11-20T00:00') }],
        });
    });
});
