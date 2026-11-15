import { createElement, lazy } from 'react';
import type { ComponentType } from 'react';

/**
 * A frame fetched on demand. When the visit opened on a page that needs it, `preload` runs before the first render and
 * `Frame` then renders the component itself: the page paints with its frame, with no Suspense gap in which its
 * listeners (⌘K) are not mounted yet. Otherwise `Frame` always renders the lazy one, so the frame never remounts.
 */
function deferred<P extends object>(
    load: () => Promise<{ default: ComponentType<P> }>,
) {
    let loaded: ComponentType<P> | undefined;
    const Lazy = lazy(load);

    return {
        async preload() {
            loaded = (await load()).default;
        },
        Frame: (props: P) => createElement<P>(loaded ?? Lazy, props),
    };
}

export const appFrame = deferred(() => import('@/layouts/app-layout'));
export const authFrame = deferred(() => import('@/layouts/auth-layout'));
export const settingsFrame = deferred(
    () => import('@/layouts/settings/layout'),
);
