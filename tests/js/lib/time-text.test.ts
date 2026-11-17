import { describe, expect, it } from 'vite-plus/test';

import { maskTimeText, parseTimeText, stepTime } from '@/lib/time-text';

describe('parseTimeText', () => {
    it.each([
        ['07:30', '07:30'],
        ['7:30', '07:30'],
        ['7.30', '07:30'],
        ['7', '07:00'],
        ['07', '07:00'],
        ['730', '07:30'],
        ['0730', '07:30'],
        ['1905', '19:05'],
        ['0:00', '00:00'],
        ['23:59', '23:59'],
        ['7 pm', '19:00'],
        ['7:30pm', '19:30'],
        ['7:30 p. m.', '19:30'],
        ['12 am', '00:00'],
        ['12:15 pm', '12:15'],
        ['9 a.m.', '09:00'],
        ['  08 : 15  ', '08:15'],
    ])('reads %j as %s', (text, value) => {
        expect(parseTimeText(text)).toEqual({ kind: 'ok', value });
    });

    it('reads an empty text as empty', () => {
        expect(parseTimeText('  ')).toEqual({ kind: 'empty' });
    });

    it.each(['24:00', '7:60', '13 pm', '0 am', 'mediodía', '12345', ':30'])(
        'reads %j as no time',
        (text) => {
            expect(parseTimeText(text)).toEqual({ kind: 'invalid' });
        },
    );
});

describe('maskTimeText', () => {
    it('adds the colon after the hour as it is typed', () => {
        expect(maskTimeText('07', '0')).toBe('07:');
    });

    it('leaves the text alone while deleting, and words as they were typed', () => {
        expect(maskTimeText('07', '07:')).toBe('07');
        expect(maskTimeText('7 p', '7 ')).toBe('7 p');
    });

    it('writes four pasted digits as a time', () => {
        expect(maskTimeText('0730', '')).toBe('07:30');
    });
});

describe('stepTime', () => {
    it('moves a time by minutes, wrapping around midnight', () => {
        expect(stepTime('07:30', 15)).toBe('07:45');
        expect(stepTime('23:50', 15)).toBe('00:05');
        expect(stepTime('00:05', -15)).toBe('23:50');
    });

    it('starts an empty or broken time at 08:00', () => {
        expect(stepTime('', 15)).toBe('08:00');
        expect(stepTime('25:00', -15)).toBe('08:00');
    });
});
