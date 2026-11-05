import type { ReactNode } from 'react';
import RouteStageDone from '@/components/telework/route-stage-done';
import RouteStageNode from '@/components/telework/route-stage-node';
import type { RouteStage as Stage } from '@/lib/telework/route';
import { cn } from '@/lib/utils';

/**
 * One stage of the route (board V2-Ruta-N037) on its line: done, folded into what was done and its link; the one the
 * center is in, open with «Aquí vas» and its tasks; ahead, with what it asks.
 */
export default function RouteStage({
    stage,
    badge,
    children,
}: {
    stage: Stage;
    badge?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <li
            aria-current={stage.state === 'current' ? 'step' : undefined}
            className={cn(
                'relative grid grid-cols-[44px_minmax(0,1fr)] gap-4 md:gap-[18px]',
                'before:absolute before:top-11 before:bottom-0 before:left-[21px] before:w-0.5 before:bg-border last:before:hidden',
                stage.state === 'done' && 'before:bg-primary',
            )}
        >
            <RouteStageNode state={stage.state} number={stage.number} />
            <div className="flex min-w-0 flex-col gap-1.5 pt-2 pb-8">
                {stage.state === 'current' && (
                    <p className="text-sm font-semibold text-primary">
                        Aquí vas
                    </p>
                )}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2
                        className={cn(
                            'text-[19px] leading-[26px] tracking-[-0.005em]',
                            stage.state === 'done'
                                ? 'font-medium text-muted-foreground'
                                : 'font-semibold',
                        )}
                    >
                        {stage.title}
                    </h2>
                    {badge}
                </div>
                {stage.state === 'done' ? (
                    <RouteStageDone stage={stage} />
                ) : (
                    <p className="text-muted-foreground">{stage.text}</p>
                )}
                {children}
            </div>
        </li>
    );
}
