import CorrectionDetail from '@/components/attendance/correction-detail';
import CorrectionList from '@/components/attendance/correction-list';
import { cn } from '@/lib/utils';
import type { CorrectionsProps } from '@/types';

/** The requests of one state: the list at the left and, at its right, the one in view with its day and what approving it would do. */
export default function CorrectionRequests({
    queue,
}: {
    queue: Extract<CorrectionsProps, { requests: unknown }>;
}) {
    const {
        requests,
        counts,
        selectedId,
        status,
        filters,
        detail,
        canResolve,
    } = queue;

    // The list beside the request in view; with none to show, the empty queue takes the whole width.
    return (
        <div
            className={cn(
                'grid items-start gap-5',
                requests.data.length > 0 &&
                    'lg:grid-cols-[380px_minmax(0,1fr)]',
            )}
        >
            <CorrectionList
                counts={counts}
                requests={requests}
                selectedId={selectedId}
                status={status}
                q={filters.q}
            />
            {detail !== null && (
                <CorrectionDetail
                    detail={detail}
                    canResolve={canResolve}
                    pending={counts.pending}
                    status={status}
                    q={filters.q}
                />
            )}
        </div>
    );
}
