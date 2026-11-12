import BarMeter from '@/components/humetria/bar-meter';
import { Badge } from '@/components/ui/badge';
import { availablePercent, balanceNote } from '@/lib/leave/balances';
import { countOf } from '@/lib/plural';
import type { LeaveBalanceRow } from '@/types';

/** The days a person can ask for: the number, «Sin días» at zero and a bar against the days given when they are known. */
export default function BalanceDays({ person }: { person: LeaveBalanceRow }) {
    const percent = availablePercent(person.available, person.granted);

    return (
        <div className="flex flex-col gap-1.5">
            <span className="flex items-center gap-2">
                <span className="font-medium tabular-nums">
                    {countOf(person.available, 'día', 'días')}
                </span>
                {person.available === 0 && (
                    <Badge variant="outline">Sin días</Badge>
                )}
            </span>
            {percent !== null ? (
                <BarMeter
                    percent={percent}
                    tooltip={`${person.available} de ${person.granted} días otorgados`}
                />
            ) : (
                <span className="text-caption text-muted-foreground">
                    {balanceNote(person)}
                </span>
            )}
        </div>
    );
}
