import { describe, expect, it } from 'vite-plus/test';

import { cn, toUrl } from '@/lib/utils';

describe('cn', () => {
    it('joins class names', () => {
        expect(cn('a', 'b')).toBe('a b');
    });

    it('returns an empty string without input', () => {
        expect(cn()).toBe('');
    });

    it('drops falsy values', () => {
        expect(cn('a', false, null, undefined, 0, '', 'b')).toBe('a b');
    });

    it('applies conditional object and array syntax', () => {
        expect(cn(['a', { b: true, c: false }])).toBe('a b');
    });

    it('lets the last conflicting Tailwind class win', () => {
        expect(cn('p-2', 'p-4')).toBe('p-4');
        expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500');
    });

    it('keeps non-conflicting Tailwind classes', () => {
        expect(cn('px-2', 'py-4')).toBe('px-2 py-4');
    });
});

describe('toUrl', () => {
    it('returns a string href as is', () => {
        expect(toUrl('/dashboard')).toBe('/dashboard');
    });

    it('returns an empty string href as is', () => {
        expect(toUrl('')).toBe('');
    });

    it('extracts the url from a Wayfinder route object', () => {
        expect(toUrl({ url: '/settings', method: 'get' })).toBe('/settings');
    });
});
