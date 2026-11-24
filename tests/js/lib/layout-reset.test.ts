import { describe, expect, it, vi } from 'vitest';
import { layoutResetter } from '@/lib/layout-reset';

describe('layoutResetter', () => {
    it("resets the frame's props when the page that arrives is another one", () => {
        const reset = vi.fn();
        const arrives = layoutResetter('tenancy/role-editor', reset);

        arrives('tenancy/roles');

        expect(reset).toHaveBeenCalledOnce();
    });

    it('keeps them while the same page reloads', () => {
        const reset = vi.fn();
        const arrives = layoutResetter('tenancy/roles', reset);

        arrives('tenancy/roles');

        expect(reset).not.toHaveBeenCalled();
    });

    it('compares with the page before, not with the first one', () => {
        const reset = vi.fn();
        const arrives = layoutResetter('a', reset);

        arrives('b');
        arrives('b');
        arrives('a');

        expect(reset).toHaveBeenCalledTimes(2);
    });
});
