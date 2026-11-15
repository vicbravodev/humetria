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
 * across, and from the top, under the toolbar, as a chart is read. Content wider than the screen even at the smallest
 * size starts at the left margin: what does not fit is a pan to the right, and no card is cut off at the left.
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

    const centered = screen.width / 2 - (box.x + box.width / 2) * zoom;
    const fits = box.width * zoom <= screen.width - 2 * MARGIN * 2 + 0.5;

    return {
        x: fits ? centered : MARGIN * 2 - box.x * zoom,
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
    screen: { width: number; height: number; root?: Box },
): { x: number; y: number; zoom: number } {
    const whole = viewportFor(all, screen);

    if (whole.zoom >= READABLE_ZOOM) {
        return whole;
    }

    const view = viewportFor(top, screen, READABLE_ZOOM);
    const { root } = screen;

    // Starting at the left must not push the root out of view (a chart far wider than the screen): then the root is centered.
    return root === undefined ||
        (root.x + root.width) * view.zoom + view.x <= screen.width - MARGIN * 2
        ? view
        : {
              ...view,
              x: screen.width / 2 - (root.x + root.width / 2) * view.zoom,
          };
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

/**
 * Moves a view to the right, just enough, so that no card is sliced by the left edge: the one that straddles it is shown
 * whole and the ones before it are left out entirely. Only the cards on the rows in view count; the view's zoom and
 * height do not change.
 */
export function clearLeftEdge(
    view: { x: number; y: number; zoom: number },
    cards: Box[],
    screen: { width: number; height: number },
): { x: number; y: number; zoom: number } {
    const shown = cards.filter(
        (card) =>
            card.y * view.zoom + view.y < screen.height &&
            (card.y + card.height) * view.zoom + view.y > 0,
    );
    let x = view.x;

    // A card pushed in may in turn sit on the edge: a few rounds settle it.
    for (let round = 0; round < 3; round += 1) {
        const sliced = shown.filter(
            (card) =>
                card.x * view.zoom + x < MARGIN &&
                (card.x + card.width) * view.zoom + x > MARGIN,
        );

        if (sliced.length === 0) {
            break;
        }

        x = Math.max(...sliced.map((card) => MARGIN - card.x * view.zoom));
    }

    return { ...view, x };
}
