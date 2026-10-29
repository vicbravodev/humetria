import type { router } from '@inertiajs/react';

type VisitOptions = NonNullable<Parameters<typeof router.visit>[1]>;

/**
 * Inertia's default visit options: moving to another page cross-fades it (View Transitions API, styled in app.css).
 * Submissions, partial reloads and visits that stay on the same path (pagination, filters) change in place, unanimated.
 */
export function pageTransition(
    href: string,
    options: Pick<VisitOptions, 'method' | 'only' | 'viewTransition'>,
    currentUrl: string,
): { viewTransition: boolean } {
    if (options.viewTransition === true) {
        return { viewTransition: true };
    }

    const isGet = (options.method ?? 'get') === 'get';
    const isPartial = (options.only ?? []).length > 0;
    const samePath =
        new URL(href, currentUrl).pathname === new URL(currentUrl).pathname;

    return { viewTransition: isGet && !isPartial && !samePath };
}
