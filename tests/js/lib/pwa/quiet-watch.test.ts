import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { QUIET_MS, quietWatch } from '@/lib/pwa/quiet-watch';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('quietWatch', () => {
    it('says nothing could be read once a while has gone by with no code in sight', () => {
        const quiet = vi.fn();
        quietWatch(quiet).arm();

        vi.advanceTimersByTime(QUIET_MS - 1);
        expect(quiet).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(quiet).toHaveBeenCalledOnce();
    });

    it('does not say it when something was read: the reading works, whatever it was', () => {
        const quiet = vi.fn();
        const watch = quietWatch(quiet);
        watch.arm();

        vi.advanceTimersByTime(QUIET_MS - 1);
        watch.clear();
        vi.advanceTimersByTime(QUIET_MS * 3);

        expect(quiet).not.toHaveBeenCalled();
    });

    it('starts the wait again when armed again, never twice at once', () => {
        const quiet = vi.fn();
        const watch = quietWatch(quiet);
        watch.arm();
        vi.advanceTimersByTime(QUIET_MS - 1);
        watch.arm();

        vi.advanceTimersByTime(QUIET_MS - 1);
        expect(quiet).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(quiet).toHaveBeenCalledOnce();
    });

    it('can be cleared with nothing armed', () => {
        expect(() => quietWatch(vi.fn()).clear()).not.toThrow();
    });
});
