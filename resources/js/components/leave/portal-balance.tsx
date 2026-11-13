import { CalendarClock } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PeriodBalanceCard from '@/components/leave/period-balance-card';
import { countOf } from '@/lib/plural';
import type { LeavePeriod } from '@/types';

/** The days the person has, by period from the oldest (the first to be used), and the ones given to them in advance. */
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
            <h2 id="leave-balance" className="text-xl font-semibold">
                Sus días
            </h2>
            {periods.length === 0 ? (
                <EmptyState
                    icon={CalendarClock}
                    title="Aún no tiene días disponibles"
                >
                    Sus días de vacaciones aparecerán aquí en su aniversario en
                    la empresa.
                </EmptyState>
            ) : (
                <>
                    <p className="rounded-xl bg-accent px-4 py-3 text-accent-foreground">
                        Tiene{' '}
                        <strong className="font-semibold">
                            {countOf(
                                available,
                                'día disponible',
                                'días disponibles',
                            )}
                        </strong>
                        . Se usan primero los del periodo más antiguo.
                        {waiting > 0 &&
                            ` ${countOf(waiting, 'día', 'días')} en espera de respuesta: todavía no se descuentan.`}
                    </p>
                    <ul className="divide-y card">
                        {periods.map((period) => (
                            <PeriodBalanceCard
                                key={`${period.year}-${period.enjoyBy}`}
                                period={period}
                            />
                        ))}
                    </ul>
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
