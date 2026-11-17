import { CalendarClock } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PeriodBalanceCard from '@/components/leave/period-balance-card';
import PortalBalanceNote from '@/components/leave/portal-balance-note';
import { countOf } from '@/lib/plural';
import type { LeavePeriod } from '@/types';

/**
 * The days the person has: one summary with the total and, only when there is more than one period, the periods from the
 * oldest (the first to be used) below it, so no figure is said twice. And the ones given to them in advance.
 */
export default function PortalBalance({
    periods,
    advanced,
    waiting,
}: {
    periods: LeavePeriod[];
    advanced: number;
    waiting: number;
}) {
    const available = periods.reduce(
        (days, period) => days + period.available,
        0,
    );

    return (
        <section aria-labelledby="leave-balance" className="grid gap-3">
            {periods.length === 0 ? (
                <>
                    <h2 id="leave-balance" className="sr-only">
                        Sus días
                    </h2>
                    <EmptyState
                        icon={CalendarClock}
                        title="Aún no tiene días disponibles"
                    >
                        Sus días de vacaciones aparecerán aquí en su aniversario
                        en la empresa.
                    </EmptyState>
                </>
            ) : (
                <>
                    <div className="grid gap-1 card p-5">
                        <h2
                            id="leave-balance"
                            className="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Días disponibles
                        </h2>
                        <p className="text-[40px] leading-[44px] font-semibold tracking-[-0.02em] tabular-nums">
                            {countOf(available, 'día', 'días')}
                        </p>
                        <PortalBalanceNote
                            periods={periods}
                            waiting={waiting}
                        />
                    </div>
                    {periods.length > 1 && (
                        <ul className="divide-y card">
                            {periods.map((period) => (
                                <PeriodBalanceCard
                                    key={`${period.year}-${period.enjoyBy}`}
                                    period={period}
                                />
                            ))}
                        </ul>
                    )}
                </>
            )}
            {advanced > 0 && (
                <p className="text-base text-muted-foreground">
                    Le adelantaron {countOf(advanced, 'día', 'días')} de
                    vacaciones que aún no le corresponden.
                </p>
            )}
        </section>
    );
}
