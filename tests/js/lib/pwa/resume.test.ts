import { describe, expect, it } from 'vitest';
import { RESUME_AFTER_MS, shouldRefreshOnResume } from '@/lib/pwa/resume';

describe('shouldRefreshOnResume', () => {
    it('waits a minute and a half away from the app: a glance at another app does not reload it', () => {
        expect(RESUME_AFTER_MS).toBe(90_000);
        expect(shouldRefreshOnResume(1_000, 1_000 + 89_999, false)).toBe(false);
        expect(shouldRefreshOnResume(1_000, 1_000 + 90_000, false)).toBe(true);
    });

    it('never reloads what the person is typing, nor when it never was hidden', () => {
        expect(shouldRefreshOnResume(1_000, 1_000 + 600_000, true)).toBe(false);
        expect(shouldRefreshOnResume(null, 600_000, false)).toBe(false);
    });
});
