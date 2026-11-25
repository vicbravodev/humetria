import { workforceText } from '@/lib/demo-requests';
import { cn } from '@/lib/utils';
import type { DemoRequestRow } from '@/types';

/** A request on the funnel: the company, who asked, what comes next and who owns it. */
export default function DemoBoardCard({
    request,
    chosen,
    onChoose,
}: {
    request: DemoRequestRow;
    chosen: boolean;
    onChoose: () => void;
}) {
    const next =
        request.status === 'scheduled' && request.scheduled_for !== null
            ? `Demo el ${request.scheduled_for}`
            : (request.next_step ?? `Llegó el ${request.received_at}`);

    return (
        <li>
            <button
                type="button"
                aria-current={chosen ? 'true' : undefined}
                onClick={onChoose}
                className={cn(
                    'flex w-full flex-col gap-1 rounded-[10px] border bg-card p-3.5 text-left transition-colors duration-150 ease-out hover:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    chosen && 'border-primary ring-1 ring-primary',
                )}
            >
                <span className="font-semibold">{request.company}</span>
                <span className="text-sm text-muted-foreground">
                    {request.name} · {workforceText(request.workforce)}
                </span>
                <span className="text-sm">{next}</span>
                <span className="text-caption text-muted-foreground">
                    {request.owner?.name ?? 'Sin responsable'}
                </span>
            </button>
        </li>
    );
}
