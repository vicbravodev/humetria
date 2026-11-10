import { describe, expect, it } from 'vitest';
import {
    dayStatus,
    doneTitle,
    evidenceText,
    firstName,
    greeting,
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

describe('evidenceText', () => {
    it('says in words how much the mark proves, with its dots', () => {
        expect(evidenceText('strong')).toEqual({
            dots: 3,
            text: 'Evidencia fuerte: QR y ubicación',
        });
        expect(evidenceText('medium')).toEqual({
            dots: 2,
            text: 'Evidencia media: QR o ubicación',
        });
        expect(evidenceText('basic')).toEqual({
            dots: 1,
            text: 'Evidencia básica: solo su llave',
        });
    });

    it('says nothing when the server gave none or one it does not know', () => {
        expect(evidenceText(null)).toBeNull();
        expect(evidenceText('corrected')).toBeNull();
        expect(evidenceText('x')).toBeNull();
    });
});
