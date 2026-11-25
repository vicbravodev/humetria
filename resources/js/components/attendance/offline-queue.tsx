import OfflineMarkDetail from '@/components/attendance/offline-mark-detail';
import OfflineMarkList from '@/components/attendance/offline-mark-list';
import OfflineRejectedList from '@/components/attendance/offline-rejected-list';
import { cn } from '@/lib/utils';
import type { CorrectionsProps } from '@/types';

/**
 * The tabs of the marks made without a connection (criterio 5.4). «Sin conexión»: the list at the left and, at its right,
 * the mark in view to verify or correct; «No aceptadas»: the deliveries that were turned away, read-only.
 */
export default function OfflineQueue({
    queue,
}: {
    queue: Extract<CorrectionsProps, { status: 'offline' | 'not_accepted' }>;
}) {
    if (queue.status === 'not_accepted') {
        return (
            <OfflineRejectedList
                deliveries={queue.offlineMarks}
                q={queue.filters.q}
            />
        );
    }

    const { offlineMarks, detail, selectedId, filters, canResolve } = queue;

    // The list beside the mark in view; with none to show, the empty list takes the whole width.
    return (
        <div
            className={cn(
                'grid items-start gap-5',
                offlineMarks.data.length > 0 &&
                    'lg:grid-cols-[380px_minmax(0,1fr)]',
            )}
        >
            <OfflineMarkList
                marks={offlineMarks}
                selectedId={selectedId}
                q={filters.q}
                flags={filters.flags}
                canResolve={canResolve}
            />
            {detail !== null && (
                <OfflineMarkDetail detail={detail} canResolve={canResolve} />
            )}
        </div>
    );
}
