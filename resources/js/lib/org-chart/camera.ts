export const MIN_ZOOM = 0.4;
export const MAX_ZOOM = 1;
/** Below this the cards cannot be read: the chart opens at this size or larger, whatever its width. */
export const READABLE_ZOOM = 0.9;
/** «Ajustar» never goes below this: a chart too big for the screen is panned, not shrunk until the names cannot be read. */
export const FIT_ZOOM = 0.5;
/** The toolbar floats over the top of the canvas: the content starts under it. */
const TOOLBAR = 72;
const MARGIN = 16;

export type Box = { x: number; y: number; width: number; height: number };

const clamp = (value: number, low: number, high: number): number =>
    Math.min(high, Math.max(low, value));

/**
 * Where the canvas looks to show a box of cards: as large as fits (never past full size nor below the minimum), centered
 * across, and from the top, under the toolbar, as a chart is read.
 */
export function viewportFor(
    box: Box,
    screen: { width: number; height: number },
    minZoom: number = MIN_ZOOM,
): { x: number; y: number; zoom: number } {
    const zoom = clamp(
        Math.min(
            (screen.width - 2 * MARGIN * 2) / box.width,
            (screen.height - TOOLBAR - 2 * MARGIN) / box.height,
        ),
        minZoom,
        MAX_ZOOM,
    );

    return {
        x: screen.width / 2 - (box.x + box.width / 2) * zoom,
        y: TOOLBAR + MARGIN - box.y * zoom,
        zoom,
    };
}

/**
 * Where the chart opens: all of it when that is still readable; otherwise the root and its first level, at a readable
 * size and from the top (the rest is a pan or «Mostrar» away).
 */
export function readableViewport(
    all: Box,
    top: Box,
    screen: { width: number; height: number },
): { x: number; y: number; zoom: number } {
    const whole = viewportFor(all, screen);

    return whole.zoom >= READABLE_ZOOM
        ? whole
        : viewportFor(top, screen, READABLE_ZOOM);
}

/** The box that holds some cards (all of them when `ids` is null) where the layout put them; null when none is there. */
export function boundsOf(
    ids: string[] | null,
    nodes: { id: string; width: number; height: number }[],
    positions: ReadonlyMap<string, { x: number; y: number }>,
): Box | null {
    const boxes = nodes.flatMap(({ id, width, height }) => {
        const at = positions.get(id);

        return at === undefined || (ids !== null && !ids.includes(id))
            ? []
            : [
                  {
                      left: at.x,
                      top: at.y,
                      right: at.x + width,
                      bottom: at.y + height,
                  },
              ];
    });

    if (boxes.length === 0) {
        return null;
    }

    const left = Math.min(...boxes.map((box) => box.left));
    const top = Math.min(...boxes.map((box) => box.top));

    return {
        x: left,
        y: top,
        width: Math.max(...boxes.map((box) => box.right)) - left,
        height: Math.max(...boxes.map((box) => box.bottom)) - top,
    };
}
