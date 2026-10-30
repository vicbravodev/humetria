import { cn } from '@/lib/utils';
import type { DemoRequestRow } from '@/types';

/** A request in the inbox's list: who, when, and from what company and size. */
export default function DemoRequestItem({
    request,
    chosen,
    onChoose,
}: {
    request: DemoRequestRow;
    chosen: boolean;
    onChoose: () => void;
}) {
    return (
        <li className="border-b last:border-b-0">
            <button
                type="button"
                aria-current={chosen ? 'true' : undefined}
                onClick={onChoose}
                className={cn(
                    'flex w-full flex-col gap-1 px-4 py-3.5 text-left transition-colors duration-150 ease-out hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                    chosen && 'bg-accent hover:bg-accent',
                )}
            >
                <span className="flex items-baseline justify-between gap-2">
                    <span className="font-medium">{request.name}</span>
                    <span className="shrink-0 text-[13px] leading-[18px] text-muted-foreground tabular-nums">
                        {request.received_at}
                    </span>
                </span>
                <span className="text-sm text-muted-foreground">
                    {request.company} · {request.workforce} trabajadores
                </span>
            </button>
        </li>
    );
}
