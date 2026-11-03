import { describe, expect, it } from 'vitest';
import { isPrivatePath } from '@/lib/private-paths';

describe('isPrivatePath', () => {
    it.each([
        '/c',
        '/c/ABCDEFGH23456789',
        '/c/ABCDEFGH23456789/follow-up',
        '/workers',
        '/workers/abc',
        '/workers/abc/centers/def',
    ])('keeps %s free of cookies', (path) => {
        expect(isPrivatePath(path)).toBe(true);
    });

    it.each([
        '/',
        '/admin/acme',
        '/campaigns',
        '/cx',
        '/portal/c/abc',
        '/workersx',
        '/admin/acme/workers',
    ])('lets %s write its preferences', (path) => {
        expect(isPrivatePath(path)).toBe(false);
    });
});
