import { describe, expect, it, vi } from 'vitest';
import { watchNewVersion } from '@/lib/pwa/new-version';

/** A stand-in for `navigator.serviceWorker`: `change()` is the browser handing the page to a new worker. */
function container(controller: object | null) {
    const listeners = new Set<() => void>();

    return {
        controller,
        addEventListener: (_: 'controllerchange', listener: () => void) =>
            listeners.add(listener),
        removeEventListener: (_: 'controllerchange', listener: () => void) =>
            listeners.delete(listener),
        change: () => listeners.forEach((listener) => listener()),
        count: () => listeners.size,
    };
}

describe('watchNewVersion', () => {
    it('says so when a worker takes over a page that already had one: the page runs the old build', () => {
        const worker = container({});
        const found = vi.fn();

        watchNewVersion(worker, found);
        worker.change();

        expect(found).toHaveBeenCalledOnce();
    });

    it('says nothing the first time a worker takes the page: it has no old build to leave', () => {
        const worker = container(null);
        const found = vi.fn();

        watchNewVersion(worker, found);
        worker.change();

        expect(found).not.toHaveBeenCalled();
    });

    it('stops listening when the page goes, and does nothing without a worker at all', () => {
        const worker = container({});
        const stop = watchNewVersion(worker, vi.fn());

        stop();

        expect(worker.count()).toBe(0);
        expect(() => watchNewVersion(undefined, vi.fn())()).not.toThrow();
    });
});
