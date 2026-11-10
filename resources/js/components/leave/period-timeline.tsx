import { Badge } from '@/components/ui/badge';
import { dateSpan } from '@/lib/leave/format';
import { countOf } from '@/lib/plural';
import type { LeaveRecordPeriod } from '@/types';

/** The periods of the person, newest first: the days each gave, what is left and the dates of its window. */
export default function PeriodTimeline({
    periods,
}: {
    periods: LeaveRecordPeriod[];
}) {
    return (
        <ol className="flex flex-col divide-y card">
            {periods.map((period) => (
                <li
                    key={period.id}
                    className="flex flex-wrap items-start justify-between gap-4 p-5"
                >
                    <div className="flex min-w-0 flex-col gap-1">
                        <p className="font-semibold">Periodo {period.year}</p>
                        <p className="text-meta text-muted-foreground">
                            Otorgado el{' '}
                            {dateSpan(
                                period.anniversaryOn,
                                period.anniversaryOn,
                            )}
                            : {countOf(period.granted, 'día', 'días')}. A
                            disfrutar antes del{' '}
                            {dateSpan(period.enjoyBy, period.enjoyBy)};
                            prescribe el{' '}
                            {dateSpan(period.prescribesOn, period.prescribesOn)}
                            .
                        </p>
                        {period.mayPrescribe && (
                            <Badge variant="warning" className="w-fit">
                                Prescribible: sigue en el saldo
                            </Badge>
                        )}
                    </div>
                    <p className="flex shrink-0 flex-col items-end">
                        <span className="text-2xl leading-8 font-semibold tabular-nums">
                            {period.available}
                        </span>
                        <span className="text-meta text-muted-foreground">
                            disponibles
                        </span>
                    </p>
                </li>
            ))}
        </ol>
    );
}
