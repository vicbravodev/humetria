import { Link } from '@inertiajs/react';
import StageBar from '@/components/humetria/stage-bar';
import { stageLine, stageStates } from '@/lib/dashboard';
import type { NavItem, RouteProgress } from '@/types';

/**
 * A work center in the route of one standard: its name, its six stages and where it is in words. A center that has not
 * answered «¿Te aplica?» gets the way to answer instead. When its card is narrow the words take a line of their own.
 */
export default function DashboardRouteRow({
    name,
    route,
    answerHref,
}: {
    name: string;
    route: RouteProgress;
    answerHref: NavItem['href'] | null;
}) {
    return (
        <li className="grid grid-cols-[minmax(0,1fr)_112px] items-center gap-x-4 gap-y-1 py-1 text-sm @[560px]:grid-cols-[minmax(140px,180px)_112px_minmax(0,1fr)]">
            <span className="truncate font-medium">{name}</span>
            {route.applies ? (
                <StageBar states={stageStates(route)} />
            ) : (
                <span className="text-muted-foreground">
                    {route.answered ? 'No aplica' : 'Falta contestar'}
                </span>
            )}
            <span className="col-span-2 text-muted-foreground @[560px]:col-span-1">
                {route.answered
                    ? stageLine(route)
                    : answerHref !== null && (
                          <Link
                              href={answerHref}
                              className="touch-hit font-medium text-primary"
                          >
                              Contesta si te aplica
                          </Link>
                      )}
            </span>
        </li>
    );
}
