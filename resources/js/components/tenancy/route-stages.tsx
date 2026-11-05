import { Link } from '@inertiajs/react';
import { Check, ChevronRight } from 'lucide-react';
import { stageStates } from '@/lib/dashboard';
import { cn } from '@/lib/utils';
import type { NavItem, RouteProgress } from '@/types';

const LINES = { done: 'Hecho', current: 'Aquí vas', pending: 'Pendiente' };

/**
 * The six stages of a route as a list (board V3-Movil): done with a check, the one the center is in outlined, the rest
 * ahead; each opens the page where it is done. Rows of 64 px, comfortable to the thumb.
 */
export default function RouteStages({
    route,
    titles,
    hrefs,
}: {
    route: RouteProgress;
    titles: string[];
    hrefs: NavItem['href'][];
}) {
    const states = stageStates(route);

    return (
        <ol aria-label="Etapas" className="overflow-hidden card">
            {titles.map((title, index) => {
                const state = states[index] ?? 'pending';
                const href = hrefs[index];

                return (
                    <li key={title} className="border-t first:border-t-0">
                        {href !== undefined && (
                            <Link
                                href={href}
                                className="grid min-h-16 grid-cols-[32px_minmax(0,1fr)_auto] items-center gap-3.5 px-4 py-2.5 hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                            >
                                <span
                                    className={cn(
                                        'inline-flex size-8 items-center justify-center rounded-full border-[1.5px] text-sm font-semibold',
                                        state === 'done' &&
                                            'border-primary bg-primary text-primary-foreground',
                                        state === 'current' &&
                                            'border-2 border-primary text-primary',
                                        state === 'pending' &&
                                            'border-input text-muted-foreground',
                                    )}
                                >
                                    {state === 'done' ? (
                                        <Check aria-hidden className="size-4" />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span>
                                    <span
                                        className={cn(
                                            'block',
                                            state === 'current'
                                                ? 'font-semibold'
                                                : 'font-medium',
                                        )}
                                    >
                                        {title}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {LINES[state]}
                                    </span>
                                </span>
                                <ChevronRight
                                    aria-hidden
                                    className="size-4 text-muted-foreground"
                                />
                            </Link>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
