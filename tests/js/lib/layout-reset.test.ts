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

    // The contract: the reset runs after the new page rendered and cleared what it set, so the page must set its props in
    // its render body (it renders again after the reset), not in an effect (which would not run again).
    it('leaves a page its props only if it sets them again when it renders after the reset', () => {
        let props = 'old page crumbs';
        const arrives = layoutResetter('old', () => {
            props = '';
        });
        const renderBody = () => {
            props = 'new page crumbs';
        };

        renderBody();
        arrives('new');
        expect(props).toBe('');

        renderBody();
        expect(props).toBe('new page crumbs');
    });
});
