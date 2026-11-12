import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import ToAnswerCard from '@/components/leave/to-answer-card';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { readingLine } from '@/lib/leave/overview';
import { index as requests } from '@/routes/tenant/vacations/requests';
import type { LeaveQueueRequest, LeaveSummary } from '@/types';

/**
 * «Por responder»: the sentence that answers what is for RH today and, under it, the first requests that reached RH as
 * cards to answer without leaving the overview. Nothing waiting is a line, not an empty box.
 */
export default function ToAnswerSection({
    summary,
    requests: rows,
    today,
}: {
    summary: LeaveSummary;
    requests: LeaveQueueRequest[];
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const asked = summary.escalated + summary.pending;

    return (
        <section
            aria-labelledby="leave-to-answer"
            className="flex flex-col gap-4"
        >
            <h2
                id="leave-to-answer"
                className="text-xl leading-7 font-semibold"
            >
                {readingLine(
                    summary.escalated,
                    rows[0]?.startsOn ?? null,
                    today,
                )}
            </h2>
            {rows.length === 0 ? (
                <p className="flex items-center gap-2 text-muted-foreground">
                    <CircleCheck aria-hidden className="size-5 text-primary" />
                    Lo que espere a un jefe lo verás aquí si no responde a
                    tiempo.
                </p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {rows.map((request, index) => (
                        <ToAnswerCard
                            key={request.id}
                            request={request}
                            today={today}
                            primary={index === 0}
                        />
                    ))}
                </ul>
            )}
            {asked > 0 && (
                <Link
                    href={requests.url(tenantSlug)}
                    className="touch-hit w-fit text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Ver las {asked} solicitadas →
                </Link>
            )}
        </section>
    );
}
