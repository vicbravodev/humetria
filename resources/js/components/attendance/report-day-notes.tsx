import { Clock, TriangleAlert } from 'lucide-react';
import { reviewText } from '@/lib/attendance/report';
import { isWarning } from '@/lib/attendance/report-person';
import type { ReportDay } from '@/types';

/** What a day says besides its hours: its corrections, what an inspector should notice and who reviewed its marks made without a connection. */
export default function ReportDayNotes({ day }: { day: ReportDay }) {
    const reviews = day.reviews ?? [];

    if (day.corrections.length + day.flags.length + reviews.length === 0) {
        return <span className="text-muted-foreground">Sin notas</span>;
    }

    return (
        <ul className="flex flex-col gap-1 text-sm">
            {day.corrections.map((correction) => (
                <li key={correction}>{correction}</li>
            ))}
            {day.flags.map((flag) => (
                <li
                    key={flag}
                    className={`flex items-start gap-1.5 ${isWarning(flag) ? 'text-destructive' : 'text-muted-foreground'}`}
                >
                    {isWarning(flag) ? (
                        <TriangleAlert
                            aria-hidden
                            className="mt-0.5 size-4 shrink-0"
                        />
                    ) : (
                        <Clock aria-hidden className="mt-0.5 size-4 shrink-0" />
                    )}
                    {flag}
                </li>
            ))}
            {reviews.map((review) => (
                <li key={reviewText(review)} className="text-muted-foreground">
                    {reviewText(review)}
                </li>
            ))}
        </ul>
    );
}
