import LimitCard from '@/components/tenancy/limit-card';
import type { LimitRow } from '@/types';

/**
 * What the company has used of the people and work centers its plan covers. Past the limit it keeps what it has and cannot
 * add; the expansion is approved here by changing the plan's seats.
 */
export default function CompanyLimits({ limits }: { limits: LimitRow[] }) {
    return (
        <section aria-labelledby="company-limits" className="grid gap-4">
            <div className="grid gap-1">
                <h2
                    id="company-limits"
                    className="text-xl font-semibold tracking-[-0.01em]"
                >
                    Límites
                </h2>
                <p className="max-w-[620px] text-sm text-muted-foreground">
                    Lo que el plan cubre. Al llegar al tope la empresa lo ve y
                    pide una ampliación; tú la apruebas cambiando el plan.
                </p>
            </div>
            {limits.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sin plan asignado: la empresa no tiene límites.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {limits.map((limit) => (
                        <LimitCard key={limit.resource} limit={limit} />
                    ))}
                </div>
            )}
        </section>
    );
}
