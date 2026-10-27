import { describe, expect, it } from 'vite-plus/test';

import { httpErrorMessage } from '@/lib/http-error-message';

describe('httpErrorMessage', () => {
    it('is the text the server sent with a 429', () => {
        expect(
            httpErrorMessage({ status: 429, data: 'Espera un momento.' }),
        ).toBe('Espera un momento.');
    });

    it('asks to reload the page when the session expired (419)', () => {
        expect(httpErrorMessage({ status: 419, data: 'Page Expired' })).toBe(
            'Tu sesión expiró; recarga la página e inténtalo de nuevo.',
        );
    });

    it('is null for any other error, so Inertia handles it as usual', () => {
        expect(
            httpErrorMessage({ status: 500, data: 'Server Error' }),
        ).toBeNull();
    });

    it('is null for a 429 without a text to show', () => {
        expect(
            httpErrorMessage({ status: 429, data: { message: 'x' } }),
        ).toBeNull();
    });

    it('is null for a 429 with an empty text', () => {
        expect(httpErrorMessage({ status: 429, data: '' })).toBeNull();
    });
});
