import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { RouteStageState } from '@/types';

const NODE: Record<RouteStageState, string> = {
    done: 'bg-primary text-primary-foreground',
    current:
        'border-[2.5px] border-primary bg-card text-primary ring-[5px] ring-accent',
    pending: 'border-[1.5px] border-input bg-card text-muted-foreground',
};

/**
 * One stage of a route on its line (boards V2-Ruta-N037 and V2-Ruta-Silla): done, folded into what was done and its link;
 * the one the center is in, open with «Aquí vas» and its tasks; ahead, with what it asks. Its round mark is a check when
 * done, its number ringed where the center is, outlined ahead.
 */
export default function RouteStage({
    stage,
    done,
    badge,
    children,
}: {
    stage: {
        number: number;
        title: string;
        text: string;
        state: RouteStageState;
    };
    /** What a done stage says in one line, and where to see it. */
    done: { summary: string; href: string | null; action: string };
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
            <span
                aria-hidden
                className={cn(
                    'relative z-10 inline-flex size-11 items-center justify-center rounded-full font-semibold',
                    NODE[stage.state],
                )}
            >
                {stage.state === 'done' ? (
                    <Check className="size-5" />
                ) : (
                    stage.number
                )}
            </span>
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
                    <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span>{done.summary}</span>
                        {done.href !== null && (
                            <Link
                                href={done.href}
                                className="touch-hit font-medium text-primary hover:underline"
                            >
                                {done.action}
                            </Link>
                        )}
                    </p>
                ) : (
                    <p className="text-muted-foreground">{stage.text}</p>
                )}
                {children}
            </div>
        </li>
    );
}
