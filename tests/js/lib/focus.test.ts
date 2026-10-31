import { describe, expect, it, vi } from 'vite-plus/test';

import { focusOnMount } from '@/lib/focus';

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
