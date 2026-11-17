import { describe, expect, it } from 'vite-plus/test';

import {
    formatDateText,
    formatMonthText,
    maskDateText,
    maskMonthText,
    parseDateText,
    parseMonthText,
} from '@/lib/date-text';
import { textProblem } from '@/lib/text-problem';

const today = new Date(2026, 10, 16);

describe('parseDateText', () => {
    it.each([
        ['07/10/2018', '2018-10-07'],
        ['7/10/2018', '2018-10-07'],
        ['7-10-18', '2018-10-07'],
        ['7.10.2018', '2018-10-07'],
        ['07102018', '2018-10-07'],
        ['7 oct 2018', '2018-10-07'],
        ['7 oct. 2018', '2018-10-07'],
        ['7 de octubre de 2018', '2018-10-07'],
        ['7 Octubre 2018', '2018-10-07'],
        ['1 sept 2026', '2026-09-01'],
        ['15 ene 26', '2026-01-15'],
        ['2018-10-07', '2018-10-07'],
        ['2018/10/7', '2018-10-07'],
        ['  07 / 10 / 2018  ', '2018-10-07'],
        ['\t7 oct 2018\n', '2018-10-07'],
        ['29/02/2024', '2024-02-29'],
    ])('reads %j as %s', (text, value) => {
        expect(parseDateText(text, {}, today)).toEqual({ kind: 'ok', value });
    });

    it.each(['', '   '])('reads %j as empty', (text) => {
        expect(parseDateText(text, {}, today)).toEqual({ kind: 'empty' });
    });

    it.each([
        '31/02/2026',
        '29/02/2026',
        '00/01/2026',
        '32/01/2026',
        '10/13/2026',
        '7 octubr 2018',
        '7 xyz 2018',
        'mañana',
        '07/10',
        '2018-10',
        '1/1/123',
        '0001-01-01',
    ])('reads %j as no date', (text) => {
        expect(parseDateText(text, {}, today)).toEqual({ kind: 'invalid' });
    });

    it('reads a two-digit year in this century up to twenty years ahead, and in the last one after that', () => {
        expect(parseDateText('1/1/46', {}, today)).toEqual({
            kind: 'ok',
            value: '2046-01-01',
        });
        expect(parseDateText('1/1/47', {}, today)).toEqual({
            kind: 'ok',
            value: '1947-01-01',
        });
        expect(parseDateText('15/3/95', {}, today)).toEqual({
            kind: 'ok',
            value: '1995-03-15',
        });
    });

    it('keeps a date inside its first and last day, both included', () => {
        const limits = { min: '2026-11-10', max: '2026-11-20' };

        expect(parseDateText('09/11/2026', limits, today)).toEqual({
            kind: 'early',
        });
        expect(parseDateText('10/11/2026', limits, today)).toEqual({
            kind: 'ok',
            value: '2026-11-10',
        });
        expect(parseDateText('20/11/2026', limits, today)).toEqual({
            kind: 'ok',
            value: '2026-11-20',
        });
        expect(parseDateText('21/11/2026', limits, today)).toEqual({
            kind: 'late',
        });
    });

    it('ignores a limit that is not a date', () => {
        expect(
            parseDateText('01/01/2000', { min: 'pronto', max: '' }, today),
        ).toEqual({ kind: 'ok', value: '2000-01-01' });
    });
});

describe('formatDateText', () => {
    it('writes a day as dd/mm/aaaa', () => {
        expect(formatDateText('2018-10-07')).toBe('07/10/2018');
    });

    it('writes nothing for what is not a day', () => {
        expect(formatDateText('')).toBe('');
        expect(formatDateText('2026-02-30')).toBe('');
    });
});

describe('maskDateText', () => {
    it('writes eight digits, typed or pasted, as a date', () => {
        expect(maskDateText('07102018')).toBe('07/10/2018');
        expect(maskDateText('07102018')).toBe('07/10/2018');
    });

    it('adds no slash while the text may still be a year being typed', () => {
        expect(maskDateText('20')).toBe('20');
        expect(maskDateText('2026-1')).toBe('2026-1');
    });

    it('leaves words, deletions and other shapes as they were typed', () => {
        expect(maskDateText('7 oct')).toBe('7 oct');
        expect(maskDateText('07/10')).toBe('07/10');
        expect(maskDateText('7/10/18')).toBe('7/10/18');
    });
});

describe('parseMonthText', () => {
    it.each([
        ['10/2018', '2018-10'],
        ['1/2026', '2026-01'],
        ['10-18', '2018-10'],
        ['102018', '2018-10'],
        ['2018-10', '2018-10'],
        ['oct 2018', '2018-10'],
        ['Octubre de 2018', '2018-10'],
        [' 03 / 2026 ', '2026-03'],
    ])('reads %j as %s', (text, value) => {
        expect(parseMonthText(text, {}, today)).toEqual({ kind: 'ok', value });
    });

    it.each(['13/2026', '0/2026', 'otoño 2026', '2026', '10/'])(
        'reads %j as no month',
        (text) => {
            expect(parseMonthText(text, {}, today)).toEqual({
                kind: 'invalid',
            });
        },
    );

    it('reads an empty text as empty', () => {
        expect(parseMonthText(' ', {}, today)).toEqual({ kind: 'empty' });
    });

    it('keeps a month inside its first and last month', () => {
        const limits = { min: '2026-03', max: '2026-10' };

        expect(parseMonthText('02/2026', limits, today)).toEqual({
            kind: 'early',
        });
        expect(parseMonthText('03/2026', limits, today)).toEqual({
            kind: 'ok',
            value: '2026-03',
        });
        expect(parseMonthText('11/2026', limits, today)).toEqual({
            kind: 'late',
        });
    });
});

describe('textProblem', () => {
    const limits = { min: '2026-11-10', max: '2026-11-20' };

    it('says how to write a day, a month or a time that is not one, in tú or in usted', () => {
        expect(textProblem('invalid', { kind: 'date', limits })).toBe(
            'No es una fecha: escríbela como 07/10/2018.',
        );
        expect(
            textProblem('invalid', { kind: 'date', limits, voice: 'usted' }),
        ).toBe('No es una fecha: escríbala como 07/10/2018.');
        expect(textProblem('invalid', { kind: 'month' })).toBe(
            'No es un mes: escríbelo como 10/2026.',
        );
        expect(textProblem('invalid', { kind: 'time' })).toBe(
            'No es una hora: escríbela como 07:30.',
        );
    });

    it('names the first or last day allowed', () => {
        expect(textProblem('early', { kind: 'date', limits })).toBe(
            'Elige una fecha desde el 10/11/2026.',
        );
        expect(
            textProblem('late', { kind: 'date', limits, voice: 'usted' }),
        ).toBe('Elija una fecha hasta el 20/11/2026.');
        expect(
            textProblem('late', {
                kind: 'month',
                limits: { min: '2026-03', max: '2026-10' },
            }),
        ).toBe('Elige un mes hasta 10/2026.');
    });
});

describe('formatMonthText and maskMonthText', () => {
    it('writes a month as mm/aaaa, and nothing for what is not one', () => {
        expect(formatMonthText('2018-10')).toBe('10/2018');
        expect(formatMonthText('2018-13')).toBe('');
    });

    it('writes six digits as a month, and leaves anything else as typed', () => {
        expect(maskMonthText('102018')).toBe('10/2018');
        expect(maskMonthText('10')).toBe('10');
        expect(maskMonthText('2026-1')).toBe('2026-1');
    });
});
