import { describe, expect, it } from 'vite-plus/test';

import {
    boundsOf,
    MAX_ZOOM,
    MIN_ZOOM,
    READABLE_ZOOM,
    readableViewport,
    viewportFor,
} from '@/lib/org-chart/camera';

const screen = { width: 1000, height: 600 };

describe('viewportFor', () => {
    it('shows small content at full size, centered across and under the toolbar', () => {
        const view = viewportFor(
            { x: 100, y: 50, width: 400, height: 100 },
            screen,
        );

        expect(view.zoom).toBe(MAX_ZOOM);
        // Its middle (x = 300) lands in the middle of the screen; its top, a little under the toolbar.
        expect(view.x).toBe(500 - 300);
        expect(view.y).toBe(72 + 16 - 50);
    });

    it('zooms out to fit wide content, with a margin at each side', () => {
        const view = viewportFor(
            { x: 0, y: 0, width: 2000, height: 200 },
            screen,
        );

        expect(view.zoom).toBeCloseTo((1000 - 64) / 2000, 5);
        expect(view.x).toBeCloseTo(500 - 1000 * view.zoom, 5);
    });

    it('fits tall content in the height that is left under the toolbar', () => {
        const view = viewportFor(
            { x: 0, y: 0, width: 200, height: 1000 },
            screen,
        );

        expect(view.zoom).toBeCloseTo((600 - 72 - 32) / 1000, 5);
    });

    it('never zooms out beyond the minimum, even if it does not fit', () => {
        const view = viewportFor(
            { x: 0, y: 0, width: 20000, height: 20000 },
            screen,
        );

        expect(view.zoom).toBe(MIN_ZOOM);
    });
});

describe('boundsOf', () => {
    const nodes = [
        { id: 'a', width: 216, height: 72 },
        { id: 'b', width: 216, height: 44 },
        { id: 'c', width: 216, height: 72 },
    ];
    const positions = new Map([
        ['a', { x: 0, y: 0 }],
        ['b', { x: 300, y: 100 }],
        ['c', { x: -50, y: 400 }],
    ]);

    it('holds the cards asked for, with their sizes', () => {
        expect(boundsOf(['a', 'b'], nodes, positions)).toEqual({
            x: 0,
            y: 0,
            width: 516,
            height: 144,
        });
    });

    it('holds all the cards when none is named, and ignores one that has no place', () => {
        expect(boundsOf(null, nodes, positions)).toEqual({
            x: -50,
            y: 0,
            width: 566,
            height: 472,
        });
        expect(boundsOf(['a', 'z'], nodes, positions)?.width).toBe(216);
    });

    it('is nothing when there is nobody to show', () => {
        expect(boundsOf(['z'], nodes, positions)).toBeNull();
        expect(boundsOf(null, [], new Map())).toBeNull();
    });
});

describe('readableViewport', () => {
    const wide = { x: 0, y: 0, width: 6000, height: 800 };
    const top = { x: 2400, y: 0, width: 1200, height: 300 };

    it('opens on the root and its first level, readable and from the top, when the whole chart would be tiny', () => {
        const view = readableViewport(wide, top, { width: 1280, height: 700 });

        expect(view.zoom).toBeGreaterThanOrEqual(READABLE_ZOOM);
        // The first level, in its middle, lands in the middle of the screen and inside it.
        expect(top.x * view.zoom + view.x).toBeGreaterThanOrEqual(0);
        expect((top.x + top.width) * view.zoom + view.x).toBeLessThanOrEqual(
            1280,
        );
        // Anchored under the toolbar, not centered down the page.
        expect(view.y).toBe(72 + 16 - top.y * view.zoom);
    });

    it('shows the whole chart when it already reads well at that size', () => {
        const small = { x: 0, y: 0, width: 1000, height: 400 };

        expect(
            readableViewport(small, top, { width: 1280, height: 700 }),
        ).toEqual(viewportFor(small, { width: 1280, height: 700 }));
    });
});
