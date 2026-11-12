import { describe, expect, it } from 'vitest';
import {
    calendarQuery,
    daysOfMonth,
    isRestDay,
    monthLabel,
    monthWord,
    stretchColumns,
    stretchText,
    weekdayInitial,
    weekOf,
    weekPeople,
    weeksOf,
} from '@/lib/leave/calendar';

describe('daysOfMonth', () => {
    it('lists the days of the month with their weekday, Monday first', () => {
        const days = daysOfMonth('2026-11');

        expect(days).toHaveLength(30);
        expect(days[0]).toEqual({ date: '2026-11-01', day: 1, weekday: 6 });
        expect(days[1]).toEqual({ date: '2026-11-02', day: 2, weekday: 0 });
        expect(days[29]).toEqual({ date: '2026-11-30', day: 30, weekday: 0 });
    });

    it('knows the length of February in leap and common years', () => {
        expect(daysOfMonth('2028-02')).toHaveLength(29);
        expect(daysOfMonth('2027-02')).toHaveLength(28);
        expect(daysOfMonth('2026-12')).toHaveLength(31);
    });

    it('lists nothing for what is not a month', () => {
        expect(daysOfMonth('noviembre')).toEqual([]);
        expect(daysOfMonth('2026-13')).toEqual([]);
    });
});

describe('stretchColumns', () => {
    it('puts a stretch of three days in its three columns', () => {
        expect(stretchColumns('2026-11-10', '2026-11-12')).toEqual({
            column: 10,
            span: 3,
        });
    });

    it('takes one column for a single day and starts at the first for a stretch cut at the start', () => {
        expect(stretchColumns('2026-11-05', '2026-11-05')).toEqual({
            column: 5,
            span: 1,
        });
        expect(stretchColumns('2026-11-01', '2026-11-03')).toEqual({
            column: 1,
            span: 3,
        });
    });

    it('reaches the last day of the month', () => {
        expect(stretchColumns('2026-11-29', '2026-11-30')).toEqual({
            column: 29,
            span: 2,
        });
    });
});

describe('weekdayInitial and monthLabel', () => {
    it('names the weekday by its initial and the month in Spanish', () => {
        expect(weekdayInitial(0)).toBe('L');
        expect(weekdayInitial(6)).toBe('D');
        expect(weekdayInitial(9)).toBe('');
        expect(monthLabel('2026-11')).toBe('noviembre de 2026');
        expect(monthLabel('x')).toBe('');
    });
});

describe('calendarQuery', () => {
    it('keeps the month and only the filters that narrow it', () => {
        const none = { center: null, area: null };

        expect(calendarQuery('2026-11', none)).toEqual({ month: '2026-11' });
        expect(
            calendarQuery('2026-12', {
                center: { value: 'c1', label: 'Planta' },
                area: { value: 'a1', label: 'Ventas' },
            }),
        ).toEqual({ month: '2026-12', work_center: 'c1', area: 'a1' });
    });
});

describe('isRestDay', () => {
    const sunday = { date: '2026-11-01', day: 1, weekday: 6 };
    const monday = { date: '2026-11-02', day: 2, weekday: 0 };
    const revolution = { date: '2026-11-16', day: 16, weekday: 0 };

    it('rests on weekends and on the holidays it is told', () => {
        expect(isRestDay(sunday, [])).toBe(true);
        expect(isRestDay(monday, [])).toBe(false);
        expect(isRestDay(revolution, [])).toBe(false);
        expect(isRestDay(revolution, ['2026-11-16'])).toBe(true);
    });
});

describe('calendarQuery with everybody', () => {
    it('asks for everybody only when it is the choice', () => {
        const none = { center: null, area: null };

        expect(calendarQuery('2026-11', none)).toEqual({ month: '2026-11' });
        expect(calendarQuery('2026-11', none, true)).toEqual({
            month: '2026-11',
            with_absence: '0',
        });
    });
});

describe('weekOf', () => {
    const days = daysOfMonth('2026-11');

    it('gives the week, Monday to Sunday, of a day inside the month', () => {
        const week = weekOf(days, '2026-11-18');

        expect(week.map((day) => day.date)).toEqual([
            '2026-11-16',
            '2026-11-17',
            '2026-11-18',
            '2026-11-19',
            '2026-11-20',
            '2026-11-21',
            '2026-11-22',
        ]);
    });

    it('cuts the weeks at the edges of the month', () => {
        expect(weekOf(days, '2026-11-01').map((day) => day.date)).toEqual([
            '2026-11-01',
        ]);
        expect(weekOf(days, '2026-11-30').map((day) => day.date)).toEqual([
            '2026-11-30',
        ]);
    });

    it('is the first week when the day is not in the month', () => {
        expect(weekOf(days, '2026-12-03')[0]?.date).toBe('2026-11-01');
    });
});

describe('stretchText', () => {
    it('writes one day alone and a span with a dash', () => {
        expect(stretchText('2026-11-17', '2026-11-17')).toBe('17');
        expect(stretchText('2026-11-17', '2026-11-18')).toBe('17–18');
    });
});

describe('weekPeople', () => {
    const week = weekOf(daysOfMonth('2026-11'), '2026-11-18');
    const person = (
        name: string,
        from: string,
        to: string,
        pending = false,
    ) => ({
        id: name,
        name,
        absences: [{ from, to, kind: 'vacation' as const, pending }],
    });

    it('keeps the people with a stretch inside the week, cut to it, in the order they came', () => {
        const people = [
            person('Alonso', '2026-11-17', '2026-11-18'),
            person('Iván', '2026-11-18', '2026-11-20', true),
            person('Otra semana', '2026-11-25', '2026-11-26'),
            person('Desde antes', '2026-11-10', '2026-11-17'),
        ];

        expect(weekPeople(people, week)).toEqual([
            { id: 'Alonso', name: 'Alonso', text: '17–18', pending: false },
            { id: 'Iván', name: 'Iván', text: '18–20', pending: true },
            { id: 'Desde antes', name: 'Desde antes', text: '16–17', pending: false },
        ]);
    });
});

describe('weeksOf', () => {
    it('groups the days of the month in weeks, Monday first, cutting the first and the last', () => {
        const weeks = weeksOf(daysOfMonth('2026-11'));

        expect(weeks.map((week) => week.map((day) => day.day))).toEqual([
            [1],
            [2, 3, 4, 5, 6, 7, 8],
            [9, 10, 11, 12, 13, 14, 15],
            [16, 17, 18, 19, 20, 21, 22],
            [23, 24, 25, 26, 27, 28, 29],
            [30],
        ]);
    });

    it('has none for what is not a month', () => {
        expect(weeksOf([])).toEqual([]);
    });
});

describe('monthWord', () => {
    it('is the name of the month alone', () => {
        expect(monthWord('2026-12')).toBe('diciembre');
        expect(monthWord('2026-02')).toBe('febrero');
        expect(monthWord('nope')).toBe('');
    });
});
