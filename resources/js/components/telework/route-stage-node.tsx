import { Check } from 'lucide-react';
import type { RouteStageState } from '@/lib/telework/route';
import { cn } from '@/lib/utils';

/** The round mark of a stage on the line: a check when done, its number ringed where the center is, outlined ahead. */
export default function RouteStageNode({
    state,
    number,
}: {
    state: RouteStageState;
    number: number;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'relative z-10 inline-flex size-11 items-center justify-center rounded-full font-semibold',
                state === 'done' && 'bg-primary text-primary-foreground',
                state === 'current' &&
                    'border-[2.5px] border-primary bg-card text-primary ring-[5px] ring-accent',
                state === 'pending' &&
                    'border-[1.5px] border-input bg-card text-muted-foreground',
            )}
        >
            {state === 'done' ? <Check className="size-5" /> : number}
        </span>
    );
}
