import { Badge } from '@/components/ui/badge';
import { workforceText } from '@/lib/demo-requests';
import { cn } from '@/lib/utils';
import type { DemoRequestRow } from '@/types';

/** A request in the list: the company and who asked, its size and origin, how long it has waited and when it came. */
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
                    'flex w-full flex-wrap items-center gap-x-4 gap-y-1.5 px-4 py-3.5 text-left transition-colors duration-150 ease-out hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring md:px-6',
                    chosen && 'bg-accent hover:bg-accent',
                )}
            >
                <span className="flex min-w-[220px] flex-1 flex-col gap-0.5">
                    <span className="font-semibold">{request.company}</span>
                    <span className="text-sm text-muted-foreground">
                        <span>{request.name}</span>
                        {` · ${workforceText(request.workforce)} · ${request.origin}`}
                    </span>
                </span>
                <Badge variant={request.waiting.tone}>
                    {request.waiting.label}
                </Badge>
                <span className="w-36 text-caption text-muted-foreground tabular-nums">
                    {request.received_at}
                </span>
            </button>
        </li>
    );
}
