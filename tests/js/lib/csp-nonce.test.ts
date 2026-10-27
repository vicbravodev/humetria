import { describe, expect, it } from 'vite-plus/test';

import { readCspNonce } from '@/lib/csp-nonce';

function pageWithMeta(nonce: string | null) {
    return {
        querySelector: (selector: string) =>
            selector === 'meta[property="csp-nonce"]' && nonce !== null
                ? { nonce }
                : null,
    };
}

describe('readCspNonce', () => {
    it('reads the nonce published in the csp-nonce meta tag', () => {
        expect(readCspNonce(pageWithMeta('abc123'))).toBe('abc123');
    });

    it('is undefined when the page has no csp-nonce meta tag', () => {
        expect(readCspNonce(pageWithMeta(null))).toBeUndefined();
    });

    it('is undefined when the meta tag carries an empty nonce', () => {
        expect(readCspNonce(pageWithMeta(''))).toBeUndefined();
    });

    it('is undefined outside a browser, where there is no document', () => {
        expect(readCspNonce(undefined)).toBeUndefined();
    });
});
