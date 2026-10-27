import { describe, expect, it } from 'vite-plus/test';

import { tooManyRequestsMessage } from '@/lib/too-many-requests';

describe('tooManyRequestsMessage', () => {
    it('is the text the server sent with a 429', () => {
        expect(
            tooManyRequestsMessage({ status: 429, data: 'Espera un momento.' }),
        ).toBe('Espera un momento.');
    });

    it('is null for any other error, so Inertia handles it as usual', () => {
        expect(
            tooManyRequestsMessage({ status: 500, data: 'Server Error' }),
        ).toBeNull();
    });

    it('is null for a 429 without a text to show', () => {
        expect(
            tooManyRequestsMessage({ status: 429, data: { message: 'x' } }),
        ).toBeNull();
    });

    it('is null for a 429 with an empty text', () => {
        expect(tooManyRequestsMessage({ status: 429, data: '' })).toBeNull();
    });
});
