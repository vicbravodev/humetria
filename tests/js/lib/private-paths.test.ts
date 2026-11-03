import { describe, expect, it } from 'vitest';
import { isPrivatePath } from '@/lib/private-paths';

describe('isPrivatePath', () => {
    it.each(['/c', '/c/ABCDEFGH23456789', '/c/ABCDEFGH23456789/follow-up'])(
        'keeps %s free of cookies',
        (path) => {
            expect(isPrivatePath(path)).toBe(true);
        },
    );

    it.each(['/', '/admin/acme', '/campaigns', '/cx', '/portal/c/abc'])(
        'lets %s write its preferences',
        (path) => {
            expect(isPrivatePath(path)).toBe(false);
        },
    );
});
