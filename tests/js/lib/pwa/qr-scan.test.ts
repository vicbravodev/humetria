import { describe, expect, it } from 'vitest';
import { cameraFailure, firstText, punchCode } from '@/lib/pwa/qr-scan';

const where = { origin: 'https://app.test', slug: 'acme' };

describe('firstText', () => {
    it('reads a plain answer', () => {
        expect(firstText('hello')).toBe('hello');
    });

    it('reads nothing when nothing was found', () => {
        expect(firstText(undefined)).toBeUndefined();
        expect(firstText([])).toBeUndefined();
    });

    it('skips the codes that could not be read', () => {
        expect(firstText([new Error('bad'), 'x'])).toBe('x');
        expect(firstText(['first', 'second'])).toBe('first');
    });

    it('reads nothing when none could be read', () => {
        expect(firstText([new Error('bad')])).toBeUndefined();
    });
});

describe('punchCode', () => {
    it('takes the code of the screen of its own company', () => {
        expect(
            punchCode('https://app.test/me/acme/punch?q=9f8e7d6c', where),
        ).toEqual({ code: '9f8e7d6c' });
        expect(
            punchCode('https://app.test/me/acme/punch?q=123.ab12', where),
        ).toEqual({ code: '123.ab12' });
    });

    it('takes a code of any center of its own company: the server says if it is the right one', () => {
        expect(
            punchCode('https://app.test/me/acme/punch?q=a-b_c.d', where),
        ).toEqual({ code: 'a-b_c.d' });
    });

    it('refuses the screen of another company', () => {
        expect(punchCode('https://app.test/me/otra/punch?q=abc', where)).toBe(
            'other-company',
        );
        expect(
            punchCode('https://app.test/me/acme-industrial/punch?q=abc', where),
        ).toBe('other-company');
    });

    it.each([
        ['another origin', 'https://elsewhere.test/me/acme/punch?q=abc'],
        ['the old address', 'https://app.test/a/acme/punch?q=abc'],
        ['another page of the company', 'https://app.test/me/acme/week?q=abc'],
        ['no code', 'https://app.test/me/acme/punch'],
        ['an empty code', 'https://app.test/me/acme/punch?q='],
        ['a code with a bar', 'https://app.test/me/acme/punch?q=a%7Cb'],
        ['a code with a space', 'https://app.test/me/acme/punch?q=a%20b'],
        ['text that is not an address', 'hello'],
        ['an address of nowhere', ''],
    ])('takes nothing from %s', (_name, text) => {
        expect(punchCode(text, where)).toBeNull();
    });

    it('takes a code of 128 characters and no more', () => {
        expect(
            punchCode(
                `https://app.test/me/acme/punch?q=${'a'.repeat(128)}`,
                where,
            ),
        ).toEqual({ code: 'a'.repeat(128) });
        expect(
            punchCode(
                `https://app.test/me/acme/punch?q=${'a'.repeat(129)}`,
                where,
            ),
        ).toBeNull();
    });

    it('does not take the code of another company with a code that is not one', () => {
        expect(
            punchCode('https://app.test/me/otra/punch?q=a%7Cb', where),
        ).toBeNull();
    });
});

describe('cameraFailure', () => {
    it.each([
        ['NotAllowedError', 'denied'],
        ['NotFoundError', 'unavailable'],
        ['NotReadableError', 'unavailable'],
        ['AbortError', 'failed'],
        ['TypeError', 'failed'],
    ])('names %s %s', (name, failure) => {
        expect(cameraFailure(new DOMException('x', name))).toBe(failure);
    });

    it('names anything that is not an error a failure', () => {
        expect(cameraFailure('nope')).toBe('failed');
        expect(cameraFailure(undefined)).toBe('failed');
    });
});
