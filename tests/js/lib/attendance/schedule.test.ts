import { describe, expect, it } from 'vite-plus/test';

import {
    hoursText,
    minutesOf,
    modeLabel,
    overDailyLimit,
    overMaximum,
    chosenText,
    peopleNames,
    replacingText,
    restLabel,
    scheduleDays,
    scheduleRibbon,
    scheduleTemplates,
    shiftLabel,
    weekTotal,
} from '@/lib/attendance/schedule';

const limits = {
    year: 2026,
    minutes: 2880,
    overtimeMinutes: 540,
    dailyMinutes: { day: 480, night: 420, mixed: 450 },
};

const week = (...days: number[]) =>
    Object.fromEntries(days.map((minutes, at) => [String(at + 1), minutes]));

describe('a schedule in a row', () => {
    it('groups the days in a row with the same hours', () => {
        expect(scheduleDays(week(480, 480, 480, 480, 480, 0, 0))).toBe(
            'L–V 8 h',
        );
        expect(scheduleDays(week(420, 420, 420, 420, 420, 420, 0))).toBe(
            'L–S 7 h',
        );
        expect(scheduleDays(week(480, 480, 480, 480, 480, 240, 0))).toBe(
            'L–V 8 h · S 4 h',
        );
        expect(scheduleDays(week(450, 0, 450, 0, 450, 0, 0))).toBe(
            'L 7 h 30 min · X 7 h 30 min · V 7 h 30 min',
        );
        expect(scheduleDays(week(0, 0, 0, 0, 0, 480, 480))).toBe('S–D 8 h');
    });

    it('says when it has no working day', () => {
        expect(scheduleDays(week(0, 0, 0, 0, 0, 0, 0))).toBe(
            'Sin días de trabajo',
        );
    });

    it('names the shift and where the person works', () => {
        expect(shiftLabel('day')).toBe('Diurna');
        expect(shiftLabel('night')).toBe('Nocturna');
        expect(shiftLabel('mixed')).toBe('Mixta');
        expect(modeLabel('onsite')).toBe('Presencial');
        expect(modeLabel('remote')).toBe('Remoto');
    });
});

describe('the hours of the week', () => {
    it('adds the seven days, a day not written as 0', () => {
        expect(weekTotal(week(480, 480, 480, 480, 480, 0, 0))).toBe(2400);
        expect(weekTotal({ '1': 60 })).toBe(60);
    });

    it("warns gently above the year's ordinary maximum, never at it", () => {
        expect(overMaximum(2880, limits)).toBeNull();
        expect(overMaximum(3000, limits)).toBe(
            'Son 2 h más que el máximo ordinario de 48 h a la semana en 2026: lo que pase de ese máximo cuenta como tiempo extra.',
        );
    });

    it("also says when the extra passes the year's weekly overtime cap, never at it", () => {
        expect(overMaximum(2880 + 540, limits)).toBe(
            'Son 9 h más que el máximo ordinario de 48 h a la semana en 2026: lo que pase de ese máximo cuenta como tiempo extra.',
        );
        expect(overMaximum(2880 + 541, limits)).toBe(
            'Son 9 h 1 min más que el máximo ordinario de 48 h a la semana en 2026: lo que pase de ese máximo cuenta como tiempo extra. Además, pasa de las 9 h de tiempo extra que la ley permite a la semana.',
        );
    });
});

describe("a day above its shift's daily limit", () => {
    it('names the night or mixed days above 7 h or 7 h 30 min, never at it', () => {
        expect(
            overDailyLimit(
                week(420, 420, 420, 420, 420, 0, 0),
                'night',
                limits,
            ),
        ).toBeNull();
        expect(
            overDailyLimit(week(480, 420, 450, 0, 0, 0, 0), 'night', limits),
        ).toBe(
            'La jornada nocturna es de hasta 7 h al día: el lunes y el miércoles tienen más.',
        );
        expect(
            overDailyLimit(week(450, 450, 0, 0, 0, 0, 0), 'mixed', limits),
        ).toBeNull();
        expect(
            overDailyLimit(week(0, 0, 0, 0, 0, 0, 480), 'mixed', limits),
        ).toBe(
            'La jornada mixta es de hasta 7 h 30 min al día: el domingo tiene más.',
        );
        expect(
            overDailyLimit(
                week(480, 480, 480, 480, 480, 0, 0),
                'night',
                limits,
            ),
        ).toBe(
            'La jornada nocturna es de hasta 7 h al día: el lunes, el martes, el miércoles, el jueves y el viernes tienen más.',
        );
    });

    it('says nothing for a day shift: its longer days count by the week (criterio 2.9)', () => {
        expect(
            overDailyLimit(week(540, 540, 540, 540, 540, 0, 0), 'day', limits),
        ).toBeNull();
    });
});

describe('the hours of a day as RH writes them', () => {
    it('reads hours with a point or a comma as minutes', () => {
        expect(minutesOf('8')).toBe(480);
        expect(minutesOf('7.5')).toBe(450);
        expect(minutesOf('7,25')).toBe(435);
        expect(minutesOf(' 0 ')).toBe(0);
    });

    it('leaves out what is not a number, for the server to say so', () => {
        expect(minutesOf('')).toBeNull();
        expect(minutesOf('ocho')).toBeNull();
    });

    it('reads only digits: no infinity, exponent or hexadecimal', () => {
        expect(minutesOf('Infinity')).toBeNull();
        expect(minutesOf('1e3')).toBeNull();
        expect(minutesOf('0x10')).toBeNull();
        expect(minutesOf('-8')).toBeNull();
        expect(minutesOf('8.5.1')).toBeNull();
        expect(minutesOf('.5')).toBe(30);
    });

    it('writes minutes as hours to edit', () => {
        expect(hoursText(480)).toBe('8');
        expect(hoursText(450)).toBe('7.5');
        expect(hoursText(0)).toBe('0');
    });
});

describe('a schedule as a ribbon', () => {
    it('is one bar per day with the minutes agreed and no overtime', () => {
        expect(scheduleRibbon(week(480, 480, 0, 240, 0, 0, 0))).toEqual([
            { worked: 480, overtime: 0 },
            { worked: 480, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 240, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
            { worked: 0, overtime: 0 },
        ]);
    });

    it('leaves a day it does not know as a rest day', () => {
        expect(scheduleRibbon({ '1': 480 })[6]).toEqual({
            worked: 0,
            overtime: 0,
        });
    });
});

describe('the rest days of a schedule', () => {
    it('names them in short', () => {
        expect(restLabel(week(480, 480, 480, 480, 480, 0, 0))).toBe(
            'Sáb y dom de descanso',
        );
        expect(restLabel(week(480, 480, 0, 480, 480, 480, 0))).toBe(
            'Mié y dom de descanso',
        );
        expect(restLabel(week(0, 480, 480, 480, 480, 480, 480))).toBe(
            'Lun de descanso',
        );
    });

    it('says when there is none, and when all of them are', () => {
        expect(restLabel(week(480, 480, 480, 480, 480, 480, 480))).toBe(
            'Sin día de descanso',
        );
        expect(restLabel(week(0, 0, 0, 0, 0, 0, 0))).toBe(
            'Todos los días de descanso',
        );
    });
});

describe('the templates to start a schedule from', () => {
    it('are Monday to Friday and Monday to Saturday of 8 h, and the night shift at its daily limit', () => {
        expect(scheduleTemplates(limits)).toEqual([
            {
                id: 'mon_fri',
                label: 'Lunes a viernes',
                shift: 'day',
                minutes: week(480, 480, 480, 480, 480, 0, 0),
            },
            {
                id: 'mon_sat',
                label: 'Lunes a sábado',
                shift: 'day',
                minutes: week(480, 480, 480, 480, 480, 480, 0),
            },
            {
                id: 'night',
                label: 'Turno nocturno',
                shift: 'night',
                minutes: week(420, 420, 420, 420, 420, 0, 0),
            },
        ]);
    });

    it('follow the night shift limit the server sends', () => {
        const night = scheduleTemplates({
            ...limits,
            dailyMinutes: { day: 480, night: 360, mixed: 450 },
        }).find((template) => template.id === 'night');

        expect(night?.minutes['1']).toBe(360);
    });
});

describe('who was chosen', () => {
    it('says how many', () => {
        expect(chosenText(1)).toBe('1 persona elegida');
        expect(chosenText(2)).toBe('2 personas elegidas');
    });

    it('says how many of them lose their schedule when the same one is set for all', () => {
        const some = { schedule: { shiftType: 'day' } };
        const none = { schedule: null };

        expect(replacingText([none, none])).toBeNull();
        expect(replacingText([some, none])).toBe(
            '1 de ellas ya tiene horario: se reemplaza.',
        );
        expect(replacingText([some, some, none])).toBe(
            '2 de ellas ya tienen horario: se reemplaza.',
        );
        expect(replacingText([some])).toBe('Ya tiene horario: se reemplaza.');
    });
});

describe('the names of those chosen', () => {
    it('are listed as a sentence, the first three in full', () => {
        expect(peopleNames(['Bob Díaz', 'Carla Ruiz'])).toBe(
            'Bob Díaz y Carla Ruiz',
        );
        expect(peopleNames(['Ana', 'Bob', 'Carla'])).toBe('Ana, Bob y Carla');
        expect(peopleNames(['Ana', 'Bob', 'Carla', 'Dora', 'Elena'])).toBe(
            'Ana, Bob, Carla y 2 más',
        );
    });
});
