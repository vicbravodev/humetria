import { describe, expect, it, vi } from 'vite-plus/test';

import { focusOnMount, revealFirstError } from '@/lib/focus';

describe('focusOnMount', () => {
    it('focuses the element as it mounts, without scrolling to it', () => {
        const focus = vi.fn();

        focusOnMount({ focus });

        expect(focus).toHaveBeenCalledWith({ preventScroll: true });
    });

    it('does nothing as the element unmounts', () => {
        expect(() => focusOnMount(null)).not.toThrow();
    });
});

describe('revealFirstError', () => {
    it('brings the first invalid field or error into view and focuses it', () => {
        const field = { scrollIntoView: vi.fn(), focus: vi.fn() };
        const root = { querySelector: vi.fn(() => field) };

        revealFirstError(root);

        expect(root.querySelector).toHaveBeenCalledWith(
            '[aria-invalid="true"], [role="alert"]',
        );
        expect(field.scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
        expect(field.focus).toHaveBeenCalledWith({ preventScroll: true });
    });

    it('does nothing when there is no error', () => {
        expect(() =>
            revealFirstError({ querySelector: () => null }),
        ).not.toThrow();
    });
});
