import { useState } from 'react';
import AdjustDialog from '@/components/leave/adjust-dialog';
import PeriodTimeline from '@/components/leave/period-timeline';
import type { LeaveRecordPeriod } from '@/types';

/** The periods of the person; whoever may manage vacations can adjust any of them. */
export default function PeriodsSection({
    employeeId,
    periods,
    canManage,
}: {
    employeeId: string;
    periods: LeaveRecordPeriod[];
    canManage: boolean;
}) {
    const [adjusting, setAdjusting] = useState<LeaveRecordPeriod | null>(null);

    return (
        <>
            <PeriodTimeline
                periods={periods}
                onAdjust={canManage ? setAdjusting : undefined}
            />
            {canManage && (
                <AdjustDialog
                    employeeId={employeeId}
                    period={adjusting}
                    onClose={() => setAdjusting(null)}
                />
            )}
        </>
    );
}
