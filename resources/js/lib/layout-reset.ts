/**
 * The frame's props a page sets (its breadcrumbs) belong to that page. Inertia clears them on a new page, except when the
 * visit keeps the state (`router.post`, `router.delete` do by default): the page that arrives would show the last one's.
 * Called after each visit with the page now on screen: when it is not the one before, it resets, and the page sets its own again
 * as it re-renders (before the swap the old page would set its own back).
 */
export function layoutResetter(
    first: string | undefined,
    reset: () => void,
): (component: string) => void {
    let shown = first;

    return (component) => {
        if (component !== shown) {
            reset();
        }

        shown = component;
    };
}
