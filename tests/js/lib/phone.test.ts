import { describe, expect, it } from 'vite-plus/test';

import {
    countryOptions,
    DEFAULT_COUNTRY,
    phoneFromStored,
    readablePhone,
    typePhone,
} from '@/lib/phone';

describe('typePhone', () => {
    it('starts on Mexico', () => {
        expect(DEFAULT_COUNTRY).toBe('MX');
    });

    it.each([
        ['5', '5'],
        ['55', '55'],
        ['551', '55 1'],
        ['551234', '55 1234'],
        ['5512345678', '55 1234 5678'],
        ['4421234567', '442 123 4567'],
    ])('masks a Mexican number while it is typed: %s', (typed, shown) => {
        expect(typePhone(typed, 'MX', '').display).toBe(shown);
    });

    it('sends a whole Mexican number in E.164', () => {
        expect(typePhone('55 1234 5678', 'MX', '')).toEqual({
            country: 'MX',
            display: '55 1234 5678',
            value: '+525512345678',
        });
    });

    it.each([
        ['+52 55 1234 5678'],
        ['+52 1 55 1234 5678'],
        ['52 1 55 1234 5678'],
        ['1 55 1234 5678'],
        ['044 55 1234 5678'],
        ['045 55 1234 5678'],
        ['(55) 1234-5678'],
        ['0052 55 1234 5678'],
    ])('reads a Mexican number pasted as %s', (pasted) => {
        expect(typePhone(pasted, 'MX', '')).toEqual({
            country: 'MX',
            display: '55 1234 5678',
            value: '+525512345678',
        });
    });

    it('switches to the country of a number pasted with its code', () => {
        expect(typePhone('+1 415 555 2671', 'MX', '')).toEqual({
            country: 'US',
            display: '(415) 555-2671',
            value: '+14155552671',
        });
    });

    it('masks the number of the country chosen', () => {
        expect(typePhone('612345678', 'ES', '')).toEqual({
            country: 'ES',
            display: '612 34 56 78',
            value: '+34612345678',
        });
    });

    it('does not take the old 1 off a number of another country', () => {
        expect(typePhone('2015550123', 'US', '').value).toBe('+12015550123');
    });

    it('keeps a code being typed until it names a country', () => {
        expect(typePhone('+3', 'MX', '')).toEqual({
            country: 'MX',
            display: '+3',
            value: '+3',
        });
    });

    it('sends what was typed when it is no number, so the server says so', () => {
        expect(typePhone('abc', 'MX', '').value).toBe('abc');
    });

    it('sends nothing for an empty field', () => {
        expect(typePhone('  ', 'MX', '55')).toEqual({
            country: 'MX',
            display: '',
            value: '',
        });
    });

    it('erases the digit before a space that was erased, instead of putting the space back', () => {
        expect(typePhone('551', 'MX', '55 1').display).toBe('51');
        expect(typePhone('55 12345678', 'MX', '55 1234 5678').display).toBe(
            '55 1235 678',
        );
    });

    it('puts back a separator erased before any digit', () => {
        expect(typePhone('415) 555-2671', 'US', '(415) 555-2671').display).toBe(
            '(415) 555-2671',
        );
    });
});

describe('phoneFromStored', () => {
    it('shows a stored Mexican number masked, on Mexico', () => {
        expect(phoneFromStored('+525512345678')).toEqual({
            country: 'MX',
            display: '55 1234 5678',
            value: '+525512345678',
        });
    });

    it('shows a stored number of another country on its country', () => {
        expect(phoneFromStored('+14155552671')).toEqual({
            country: 'US',
            display: '(415) 555-2671',
            value: '+14155552671',
        });
    });

    it('starts empty on Mexico', () => {
        expect(phoneFromStored(null)).toEqual({
            country: 'MX',
            display: '',
            value: '',
        });
        expect(phoneFromStored(undefined).country).toBe('MX');
    });
});

describe('countryOptions', () => {
    it('lists Mexico first and the rest by their Spanish name, each with its code', () => {
        const options = countryOptions();

        expect(options[0]).toEqual({
            code: 'MX',
            name: 'México',
            prefix: '+52',
        });
        expect(options.find((option) => option.code === 'US')).toEqual({
            code: 'US',
            name: 'Estados Unidos',
            prefix: '+1',
        });
        expect(options.length).toBeGreaterThan(200);

        const names = options.slice(1).map((option) => option.name);

        expect(names).toEqual(
            [...names].sort((a, b) => a.localeCompare(b, 'es')),
        );
    });
});

describe('readablePhone', () => {
    it('shows a Mexican number as dialed in Mexico', () => {
        expect(readablePhone('+525512345678')).toBe('55 1234 5678');
    });

    it('shows a number of another country with its code', () => {
        expect(readablePhone('+14155552671')).toBe('+1 415 555 2671');
    });

    it('leaves what is no number as it is', () => {
        expect(readablePhone('n/a')).toBe('n/a');
    });
});
