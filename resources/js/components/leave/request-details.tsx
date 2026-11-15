import { requestDetails } from '@/lib/leave/queue';
import type { LeaveQueueRequest } from '@/types';

/** The facts of a request that are not its days: whose it was to answer, who recorded it, the note and the reason of a rejection. */
export default function RequestDetails({
    request,
}: {
    request: LeaveQueueRequest;
}) {
    const details = requestDetails(request);

    if (details.length === 0) {
        return null;
    }

    return (
        <dl className="flex flex-col gap-3 text-meta">
            {details.map(([label, text]) => (
                <div key={label} className="flex flex-col gap-0.5">
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd>{text}</dd>
                </div>
            ))}
        </dl>
    );
}
