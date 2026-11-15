import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dateSpan } from '@/lib/leave/format';
import { countOf } from '@/lib/plural';
import type { LeaveRecordPeriod } from '@/types';

/** The periods of the person, newest first: the days each gave, what is left and the dates of its window. */
export default function PeriodTimeline({
    periods,
    onAdjust,
}: {
    periods: LeaveRecordPeriod[];
    /** Present only for whoever may adjust. */
    onAdjust?: ((period: LeaveRecordPeriod) => void) | undefined;
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
                        {period.overdue && !period.mayPrescribe && (
                            <Badge variant="warning" className="w-fit">
                                Vencido: sigue en el saldo
                            </Badge>
                        )}
                        {period.mayPrescribe && (
                            <Badge variant="warning" className="w-fit">
                                Prescribible: sigue en el saldo
                            </Badge>
                        )}
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-2">
                        <p className="flex flex-col items-end">
                            <span className="text-2xl leading-8 font-semibold tabular-nums">
                                {period.available}
                            </span>
                            <span className="text-meta text-muted-foreground">
                                disponibles
                            </span>
                        </p>
                        {onAdjust !== undefined && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onAdjust(period)}
                                aria-label={`Ajustar el periodo ${period.year}`}
                            >
                                Ajustar
                            </Button>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
