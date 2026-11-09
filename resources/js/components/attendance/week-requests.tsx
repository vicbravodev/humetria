import { Badge } from '@/components/ui/badge';
import { requestStatus } from '@/lib/attendance/correction';
import { markName } from '@/lib/attendance/next-step';
import type { WeekRequest } from '@/lib/attendance/correction';
import { dayTitle } from '@/lib/attendance/week-text';

const BADGES: Record<string, 'warning' | 'success' | 'secondary'> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'secondary',
};

/** What the person asked RH to correct in the week, where each request stands and RH's note. */
export default function WeekRequests({
    requests,
}: {
    requests: WeekRequest[];
}) {
    if (requests.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="week-requests-title"
            className="flex flex-col gap-3 card p-5"
        >
            <h2
                id="week-requests-title"
                className="text-xl leading-7 font-semibold"
            >
                Sus solicitudes de corrección
            </h2>
            <ul className="flex flex-col divide-y">
                {requests.map((request) => (
                    <li
                        key={request.id}
                        className="flex flex-col gap-1 py-3 text-base"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span>
                                {markName(request.type)} ·{' '}
                                <span className="tabular-nums">
                                    {dayTitle(request.date)}, {request.time}
                                </span>
                            </span>
                            <Badge
                                variant={BADGES[request.status] ?? 'warning'}
                            >
                                {requestStatus(request.status)}
                            </Badge>
                        </div>
                        {request.note !== null && (
                            <p className="text-muted-foreground">
                                Recursos Humanos: {request.note}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
