import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { stageLine } from '@/lib/dashboard';
import type { NavItem, RouteProgress } from '@/types';

/**
 * A route that does not start (decisión A2): the center declared that the standard does not apply, with its certificate,
 * or it still has to answer «¿Te aplica?».
 */
export default function RouteNotApplicable({
    route,
    answerHref,
    certificateHref,
}: {
    route: RouteProgress;
    answerHref: NavItem['href'] | null;
    certificateHref: NavItem['href'] | null;
}) {
    const href = route.answered ? certificateHref : answerHref;

    return (
        <div className="flex flex-col items-start gap-4 card p-6">
            <h2 className="text-xl font-semibold">
                {route.answered
                    ? 'No aplica en este centro'
                    : 'Primero, ¿te aplica?'}
            </h2>
            <p className="text-muted-foreground">
                {route.answered
                    ? stageLine(route)
                    : 'Contesta si esta norma aplica en el centro y armamos tu ruta, o te dejamos constancia de que no te aplica.'}
            </p>
            {href !== null && (
                <Button asChild>
                    <Link href={href}>
                        {route.answered
                            ? 'Ver constancia'
                            : 'Contesta si te aplica'}
                    </Link>
                </Button>
            )}
        </div>
    );
}
