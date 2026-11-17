import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { countOf, plural } from '@/lib/plural';
import type { LeavePeriod } from '@/types';

/** The line under the person's total: the period itself when there is one, the order of use when there are several. */
export default function PortalBalanceNote({
    periods,
    waiting,
}: {
    periods: LeavePeriod[];
    waiting: number;
}) {
    const [only] = periods;

    return (
        <div className="grid justify-items-start gap-2 text-muted-foreground">
            <p>
                {periods.length === 1 && only !== undefined
                    ? `Periodo ${only.year}, de ${only.granted} ${plural(only.granted, 'día otorgado', 'días otorgados')}. Disfrútelos antes del ${describeDate(only.enjoyBy)}.`
                    : 'Se usan primero los del periodo más antiguo.'}
                {waiting > 0 &&
                    ` ${countOf(waiting, 'día', 'días')} en espera de respuesta: todavía no se descuentan.`}
            </p>
            {periods.length === 1 && only?.mayPrescribe === true && (
                <Badge variant="warning">Podría prescribir</Badge>
            )}
        </div>
    );
}
