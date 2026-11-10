import { Badge } from '@/components/ui/badge';
import { dateSpan } from '@/lib/leave/format';
import { owedDays } from '@/lib/leave/record';
import { countOf } from '@/lib/plural';
import type { LeaveRecordTermination } from '@/types';

/**
 * What a person who left is owed, in days (LFT art. 79): the days they have left plus the part of the year in course.
 * Never an amount: what they are worth is the company's payroll.
 */
export default function TerminationCard({
    termination,
}: {
    termination: LeaveRecordTermination;
}) {
    return (
        <section
            aria-labelledby="termination-title"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="termination-title" className="text-lg font-semibold">
                    Días al terminar la relación
                </h2>
                {termination.settledOn === null ? (
                    <Badge variant="warning">Por finiquitar</Badge>
                ) : (
                    <Badge variant="success">
                        Finiquitado el{' '}
                        {dateSpan(termination.settledOn, termination.settledOn)}
                    </Badge>
                )}
            </div>
            <dl className="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt className="text-meta text-muted-foreground">Baja</dt>
                    <dd className="font-medium">
                        {dateSpan(
                            termination.terminatedOn,
                            termination.terminatedOn,
                        )}
                    </dd>
                </div>
                <div>
                    <dt className="text-meta text-muted-foreground">
                        Días pendientes
                    </dt>
                    <dd className="font-medium tabular-nums">
                        {countOf(termination.pendingDays, 'día', 'días')}
                    </dd>
                </div>
                <div>
                    <dt className="text-meta text-muted-foreground">
                        Proporcionales del año en curso
                    </dt>
                    <dd className="font-medium tabular-nums">
                        {countOf(termination.proportionalDays, 'día', 'días')}
                        {' · '}
                        {owedDays(
                            termination.pendingDays,
                            termination.proportionalDays,
                        )}{' '}
                        en total
                    </dd>
                </div>
            </dl>
        </section>
    );
}
