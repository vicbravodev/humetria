import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { OPEN_FIRST_STEPS, openFirstSteps } from '@/lib/first-steps/open';

describe('openFirstSteps', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('asks to open the panel with a window event', () => {
        const target = new EventTarget();
        const listener = vi.fn();
        target.addEventListener(OPEN_FIRST_STEPS, listener);
        vi.stubGlobal('window', target);

        openFirstSteps();

        expect(OPEN_FIRST_STEPS).toBe('first-steps:open');
        expect(listener).toHaveBeenCalledTimes(1);
    });
});
