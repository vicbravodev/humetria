import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { stop } from '@/routes/impersonation';

/**
 * Every tour of the session is done (board Rec-13): back to the room, where the session ends and the demo is cleaned.
 * Leaving the company flushes the pages fetched ahead: they were the impersonated owner's.
 */
export default function GuideFinished() {
    return (
        <>
            <p>
                Cuando termines, vuelve a la sala y termina la sesión: la demo
                se limpia, con las personas del público.
            </p>
            <Button asChild size="sm" className="self-start max-md:h-11">
                <Link
                    href={stop()}
                    as="button"
                    onClick={() => router.flushAll()}
                >
                    Volver a la sala
                </Link>
            </Button>
        </>
    );
}
