import { describe, expect, it } from 'vitest';
import {
    clockOffset,
    dayStatus,
    doneTitle,
    firstName,
    greeting,
    markClock,
    markHow,
} from '@/lib/attendance/punch-view';

describe('greeting', () => {
    it.each([
        [0, 'Buenos días'],
        [11, 'Buenos días'],
        [12, 'Buenas tardes'],
        [18, 'Buenas tardes'],
        [19, 'Buenas noches'],
        [23, 'Buenas noches'],
    ])('says it at %i h', (hour, text) => {
        expect(greeting(hour)).toBe(text);
    });
});

describe('dayStatus', () => {
    it('tells where the day stands from the marks of the person, when the server gave them', () => {
        expect(
            dayStatus(
                ['break_start', 'end'],
                [{ type: 'start', time: '08:02' }],
            ),
        ).toEqual({
            kind: 'working',
            title: 'Está trabajando',
            text: 'Entrada 08:02',
        });
        expect(
            dayStatus(
                ['break_end', 'end'],
                [
                    { type: 'start', time: '08:02' },
                    { type: 'break_start', time: '13:00' },
                ],
            ),
        ).toEqual({
            kind: 'break',
            title: 'Está en su comida',
            text: 'Salió a comer 13:00',
        });
        expect(
            dayStatus(
                ['break_start', 'end'],
                [
                    { type: 'start', time: '08:02' },
                    { type: 'break_start', time: '13:00' },
                    { type: 'break_end', time: '13:30' },
                ],
            ),
        ).toEqual({
            kind: 'working',
            title: 'Está trabajando',
            text: 'Regresó de comer 13:30',
        });
        expect(
            dayStatus(
                ['start'],
                [
                    { type: 'start', time: '08:02' },
                    { type: 'end', time: '17:01' },
                ],
            ),
        ).toEqual({
            kind: 'done',
            title: 'Jornada terminada',
            text: 'Salida 17:01',
        });
    });

    it('tells only what the next steps allow when the marks are not known (never the marks of whoever marked before)', () => {
        expect(dayStatus(['start'], null)).toMatchObject({
            kind: 'ready',
            title: 'Listo para su entrada',
        });
        expect(dayStatus(['break_end', 'end'], null)).toMatchObject({
            kind: 'break',
            title: 'Está en su comida',
        });
        expect(dayStatus(['break_start', 'end'], null)).toMatchObject({
            kind: 'working',
            title: 'Está trabajando',
        });
    });

    it('does not say a thing of an empty day', () => {
        expect(dayStatus(['start'], []).title).toBe('Listo para su entrada');
    });
});

describe('firstName', () => {
    it('is the first word of the name, to greet the person as a person', () => {
        expect(firstName('Ana López')).toBe('Ana');
        expect(firstName('  María del Carmen Ruiz ')).toBe('María');
        expect(firstName('Beyoncé')).toBe('Beyoncé');
        expect(firstName('')).toBe('');
    });

    it.each([
        ['Ing. Carlos Ruiz', 'Carlos'],
        ['Lic. Ana López', 'Ana'],
        ['Dr. Luis Soto', 'Luis'],
        ['Dra. María Pérez', 'María'],
        ['Mtro. Pedro Gil', 'Pedro'],
        ['Mtra. Elena Cruz', 'Elena'],
        ['C.P. Rosa Vega', 'Rosa'],
        ['lic. ana lópez', 'ana'],
        ['Ing Carlos Ruiz', 'Carlos'],
    ])('greets %s as the person, not the title', (name, first) => {
        expect(firstName(name)).toBe(first);
    });

    it('keeps a name that is only a title, so the greeting is never empty', () => {
        expect(firstName('Ing.')).toBe('Ing.');
        expect(firstName('  Dra. ')).toBe('Dra.');
    });

    it('does not take a real name for a title', () => {
        expect(firstName('Dora Luz')).toBe('Dora');
        expect(firstName('Licona Ruiz')).toBe('Licona');
    });
});

describe('doneTitle', () => {
    it.each([
        ['start', 'Entrada registrada'],
        ['break_start', 'Salida a comer registrada'],
        ['break_end', 'Regreso de comer registrado'],
        ['end', 'Salida registrada'],
        ['other', 'Marca registrada'],
    ])('names %s', (type, text) => {
        expect(doneTitle(type)).toBe(text);
    });
});

describe('markHow', () => {
    it('says in words, without a measure, how the mark was taken (hallazgos 20 y 21)', () => {
        expect(markHow('strong')).toBe('Con QR y ubicación');
        expect(markHow('medium')).toBe('Con QR o ubicación');
        expect(markHow('basic')).toBe('Con su llave');
        expect(markHow('corrected')).toBe('Corregida por RH');
    });

    it('says a mark in the field was taken in the field, with or without a precise position', () => {
        expect(markHow('strong', 'field')).toBe('En campo, con su ubicación');
        expect(markHow('medium', 'field')).toBe(
            'En campo, sin ubicación precisa',
        );
        expect(markHow('strong', 'outside_zone')).toBe('Con QR y ubicación');
    });

    it('says nothing when the server gave none or one it does not know', () => {
        expect(markHow(null)).toBeNull();
        expect(markHow('x')).toBeNull();
    });
});

describe('markClock', () => {
    it("is the time alone at the person's center, and says whose hour it is when the mark was taken in another zone", () => {
        expect(markClock({ time: '09:00' })).toBe('09:00');
        expect(markClock({ time: '07:00', clock: 'America/Tijuana' })).toBe(
            '07:00 · hora de Tijuana',
        );
        expect(markClock({ time: '10:00', clock: 'America/Cancun' })).toBe(
            '10:00 · hora de Cancún',
        );
        expect(markClock({ time: '09:00', clock: 'America/Mexico_City' })).toBe(
            '09:00 · hora de Ciudad de México',
        );
        expect(markClock({ time: '09:00', clock: 'Europe/Madrid' })).toBe(
            '09:00 · hora de Madrid',
        );
    });
});

describe('clockOffset', () => {
    it("is how far the phone's clock is from the server's, so the page shows the server's time", () => {
        expect(clockOffset(1_000_000, 1_000_000)).toBe(0);
        // The phone is 2 hours behind, or a day ahead.
        expect(clockOffset(10_000_000, 2_800_000)).toBe(7_200_000);
        expect(clockOffset(0, 86_400_000)).toBe(-86_400_000);
    });
});
