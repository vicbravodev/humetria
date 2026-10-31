import { Link, router, usePage } from '@inertiajs/react';
import { stop } from '@/routes/impersonation';

/**
 * While a superadmin works as someone else, a strip over the frame says as whom, with the way back.
 * Leaving flushes the prefetched pages: they were the impersonated user's.
 */
export function ImpersonationBanner() {
    const { impersonator, auth } = usePage().props;

    if (impersonator === null) {
        return null;
    }

    return (
        <div
            data-test="impersonation-banner"
            className="sticky top-0 z-30 bg-primary px-4 py-1.5 text-center text-sm text-primary-foreground"
        >
            Estás viendo Humetría como {auth.user.name} ·{' '}
            <Link
                href={stop()}
                as="button"
                onClick={() => router.flushAll()}
                data-test="stop-impersonation"
                className="touch-hit cursor-pointer rounded-sm font-medium underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-foreground"
            >
                Salir
            </Link>
        </div>
    );
}
