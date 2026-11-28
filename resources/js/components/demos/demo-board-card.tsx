import { Badge } from '@/components/ui/badge';
import { demoCardLine } from '@/lib/demo-requests';
import { cn } from '@/lib/utils';
import type { DemoRequestRow } from '@/types';

/** A request on the funnel (tablero Demos): the company, its size and who it is for, how long it has waited and its origin. */
export default function DemoBoardCard({
    request,
    chosen,
    onChoose,
}: {
    request: DemoRequestRow;
    chosen: boolean;
    onChoose: () => void;
}) {
    return (
        <li>
            <button
                type="button"
                aria-current={chosen ? 'true' : undefined}
                onClick={onChoose}
                className={cn(
                    'flex w-full flex-col gap-2 rounded-[10px] border bg-card p-3.5 text-left transition-colors duration-150 ease-out hover:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    chosen &&
                        'border-2 border-primary p-[13px] hover:border-primary',
                )}
            >
                <span className="font-semibold">{request.company}</span>
                <span className="text-sm text-muted-foreground">
                    {demoCardLine(request.workforce, request.profile)}
                </span>
                <span className="flex flex-wrap items-center gap-1.5">
                    <Badge variant={request.waiting.tone}>
                        {request.waiting.label}
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                        {request.origin}
                    </span>
                </span>
            </button>
        </li>
    );
}
