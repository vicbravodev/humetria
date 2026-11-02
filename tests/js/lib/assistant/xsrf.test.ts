import { describe, expect, it } from 'vite-plus/test';
import { xsrfToken } from '@/lib/assistant/xsrf';

describe('xsrfToken', () => {
    it('reads the token Laravel keeps in its cookie, decoded', () => {
        expect(xsrfToken('a=1; XSRF-TOKEN=abc%3D%3D; b=2')).toBe('abc==');
    });

    it('is null when the cookie is not there', () => {
        expect(xsrfToken('a=1; b=2')).toBeNull();
        expect(xsrfToken('')).toBeNull();
    });

    it('does not take a cookie that only ends like it', () => {
        expect(xsrfToken('NOT-XSRF-TOKEN=nope')).toBeNull();
    });
});
