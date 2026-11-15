import { router, usePage } from '@inertiajs/react';
import { hide, welcome } from '@/routes/tenant/first-steps';

/**
 * What the owner can do with the first steps: mark the welcome as seen, or hide them. Whoever works as the owner changes
 * nothing of theirs: `hide` is then undefined and `seen` does nothing.
 */
export function useFirstStepsActions(): {
    welcomePending: boolean;
    seen: () => void;
    hide: (() => void) | undefined;
} {
    const { tenant, impersonator, firstSteps } = usePage().props;
    const slug = tenant?.slug;
    const mine = impersonator === null && slug !== undefined;

    return {
        // The welcome is for the owner who has not seen it, never for a superadmin working as them.
        welcomePending:
            impersonator === null &&
            firstSteps?.welcomed === false &&
            firstSteps.hidden === false,
        seen: () => {
            if (mine) {
                router.post(
                    welcome.url(slug),
                    {},
                    { preserveScroll: true, preserveState: true },
                );
            }
        },
        hide: mine ? () => router.post(hide.url(slug)) : undefined,
    };
}
