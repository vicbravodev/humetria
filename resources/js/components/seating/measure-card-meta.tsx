import Initials from '@/components/humetria/initials';
import { describeDate } from '@/lib/calendar-date';
import type { MeasureRow } from '@/types';

/** Who answers for a measure and by when; past its date, it says so. */
export default function MeasureCardMeta({ measure }: { measure: MeasureRow }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span className="flex items-center gap-2">
                {measure.ownerName !== null && (
                    <Initials name={measure.ownerName} />
                )}
                {measure.ownerName ?? 'Sin responsable'}
            </span>
            {measure.dueOn !== null && (
                <span
                    className={
                        measure.overdue
                            ? 'font-medium text-destructive'
                            : 'text-muted-foreground'
                    }
                >
                    {measure.overdue && 'Vencida · '}
                    {describeDate(measure.dueOn) ?? measure.dueOn}
                </span>
            )}
        </div>
    );
}
