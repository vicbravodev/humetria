import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { plural } from '@/lib/plural';
import type { LeavePeriod } from '@/types';

/** One vacation period of the person: how many days it still has, until when to enjoy them and whether it could prescribe. */
export default function PeriodBalanceCard({ period }: { period: LeavePeriod }) {
    return (
        <li className="flex items-start justify-between gap-4 p-4">
            <div className="flex min-w-0 flex-col gap-1">
                <p className="text-base font-semibold">Periodo {period.year}</p>
                <p className="text-base text-muted-foreground">
                    De {period.granted}{' '}
                    {plural(period.granted, 'día otorgado', 'días otorgados')}.
                    Disfrútelos antes del {describeDate(period.enjoyBy)}.
                </p>
                {period.mayPrescribe && (
                    <Badge variant="warning">Podría prescribir</Badge>
                )}
            </div>
            <p className="flex shrink-0 flex-col items-end">
                <span className="text-2xl leading-8 font-semibold tabular-nums">
                    {period.available}
                </span>
                <span className="text-sm text-muted-foreground">
                    {plural(period.available, 'disponible', 'disponibles')}
                </span>
            </p>
        </li>
    );
}
