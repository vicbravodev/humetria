/**
 * The frame's props a page sets (its breadcrumbs) belong to that page. Inertia clears them on a new page, except when the
 * visit keeps the state (`router.post`, `router.delete` do by default): the page that arrives would show the last one's.
 * Called after each visit with the page now on screen: when it is not the one before, it resets, and the page sets its own again
 * as it re-renders (before the swap the old page would set its own back).
 *
 * Contract, and why it is fragile: Inertia renders the new page before `navigate` fires, so the reset clears the props the
 * page has just set during that first render. They come back only because the reset changes the store, which re-renders
 * the page, and the page calls `setLayoutProps` again in its render body. So a page must call `setLayoutProps` while
 * rendering, never inside an effect: an effect would not run again after the reset and the page would lose its breadcrumbs.
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
