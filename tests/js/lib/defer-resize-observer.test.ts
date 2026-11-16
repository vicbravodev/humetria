import { describe, expect, it, vi } from 'vitest';
import { deferResizeObserver } from '@/lib/defer-resize-observer';

type Entries = ResizeObserverEntry[];

/** A browser's ResizeObserver that hands its callback over when told to (`deliver`), and a clock of frames. */
function fakeBrowser() {
    const frames: FrameRequestCallback[] = [];
    const observers: FakeObserver[] = [];

    class FakeObserver {
        disconnected = false;

        constructor(readonly callback: ResizeObserverCallback) {
            observers.push(this);
        }

        observe() {}

        unobserve() {}

        disconnect() {
            this.disconnected = true;
        }

        deliver(entries: Entries) {
            this.callback(entries, this);
        }
    }

    const scope = {
        ResizeObserver: FakeObserver,
        requestAnimationFrame: (callback: FrameRequestCallback) =>
            frames.push(callback),
    };

    return {
        scope,
        observers,
        nextFrame: () => frames.splice(0).forEach((frame) => frame(0)),
    };
}

describe('deferResizeObserver', () => {
    it('gives the callback its entries on the next frame, never while the browser delivers them', () => {
        const { scope, observers, nextFrame } = fakeBrowser();
        deferResizeObserver(scope);
        const callback = vi.fn();
        new scope.ResizeObserver(callback);
        const entries: Entries = [];

        observers[0]?.deliver(entries);

        expect(callback).not.toHaveBeenCalled();
        nextFrame();
        expect(callback).toHaveBeenCalledOnce();
        expect(callback.mock.calls[0]?.[0]).toBe(entries);
    });

    it('hands the callback the observer it was made for', () => {
        const { scope, observers, nextFrame } = fakeBrowser();
        deferResizeObserver(scope);
        const callback = vi.fn();
        const made = new scope.ResizeObserver(callback);

        observers[0]?.deliver([]);
        nextFrame();

        expect(callback.mock.calls[0]?.[1]).toBe(made);
    });

    it('does not call the callback of an observer that was disconnected while a frame was waiting', () => {
        const { scope, observers, nextFrame } = fakeBrowser();
        deferResizeObserver(scope);
        const callback = vi.fn();
        const made = new scope.ResizeObserver(callback);

        observers[0]?.deliver([]);
        made.disconnect();
        nextFrame();

        expect(callback).not.toHaveBeenCalled();
        expect(observers[0]?.disconnected).toBe(true);
    });

    it('does nothing where there is no ResizeObserver, as on the server', () => {
        const scope = {
            requestAnimationFrame: () => 0,
        };

        expect(() => deferResizeObserver(scope)).not.toThrow();
        expect('ResizeObserver' in scope).toBe(false);
    });
});
