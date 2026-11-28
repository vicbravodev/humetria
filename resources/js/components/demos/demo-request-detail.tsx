import type { Ref } from 'react';
import DemoRequestActions from '@/components/demos/demo-request-actions';
import DemoRequestFacts from '@/components/demos/demo-request-facts';
import DemoRequestHandling from '@/components/demos/demo-request-handling';
import { Badge } from '@/components/ui/badge';
import type { DemoRequestRow } from '@/types';

/**
 * The request chosen, in the fixed panel at the right (tablero Demos): the company with how long it has waited, its
 * data line by line, what it wrote, its actions in a column, and the follow-up of the team under them.
 */
export default function DemoRequestDetail({
    ref,
    request,
    canManage,
}: {
    /** Takes the focus when a request is chosen on a phone. */
    ref?: Ref<HTMLElement>;
    request: DemoRequestRow;
    canManage: boolean;
}) {
    return (
        <article
            ref={ref}
            tabIndex={-1}
            aria-labelledby={`demo-request-${request.id}`}
            data-test="demo-request-detail"
            className="min-w-0 card focus:outline-none xl:sticky xl:top-6 xl:w-[360px] xl:shrink-0"
        >
            <div className="flex items-center gap-3 border-b px-5 py-4 md:px-6">
                <h2
                    id={`demo-request-${request.id}`}
                    className="min-w-0 flex-1 text-base font-semibold"
                >
                    {request.company}
                </h2>
                <Badge variant={request.waiting.tone}>
                    {request.waiting.label}
                </Badge>
            </div>
            <DemoRequestFacts request={request} />
            {request.message !== null && (
                <div className="flex flex-col gap-1 border-t px-5 py-3.5 md:px-6">
                    <h3 className="text-muted-foreground">Mensaje</h3>
                    <p className="whitespace-pre-line">{request.message}</p>
                </div>
            )}
            <DemoRequestActions request={request} canManage={canManage} />
            <DemoRequestHandling request={request} canManage={canManage} />
        </article>
    );
}
