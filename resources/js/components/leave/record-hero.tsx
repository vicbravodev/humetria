import { Link, router } from '@inertiajs/react';
import ArcMeter from '@/components/humetria/arc-meter';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { milestones } from '@/lib/leave/record';
import { links } from '@/routes/tenant/vacations/balances';
import { show as statement } from '@/routes/tenant/vacations/statements';
import type { LeaveRecordPeriod, LeaveRecordStatement } from '@/types';

/**
 * The protagonist of the record: the current period as an arch with the percentage of its days still available, and the
 * three dates that matter as a line of time. At the side, the days given in advance and the statement still to confirm.
 */
export default function RecordHero({
    period,
    advanced,
    waiting,
    open,
    canManage,
    personId,
}: {
    period: LeaveRecordPeriod;
    advanced: number;
    /** Working days of vacations asked for and not answered yet: they are not off the balance. */
    waiting: number;
    open: LeaveRecordStatement | null;
    canManage: boolean;
    personId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-label={`Periodo ${period.year}`}
            className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        >
            <div className="flex flex-col gap-6 card p-6 md:flex-row md:items-center">
                <div className="flex flex-col items-center gap-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        Periodo {period.year}
                    </p>
                    <ArcMeter
                        value={period.available}
                        max={period.granted}
                        showPercent
                        label={`${period.available} de ${period.granted} días disponibles`}
                    />
                    <p className="text-meta text-muted-foreground">
                        {period.available} de {period.granted} días disponibles
                        {waiting > 0 && ` · ${waiting} en espera`}
                    </p>
                </div>
                <ol className="flex flex-1 flex-col gap-4 border-l pl-5 md:ml-4">
                    {milestones(period).map((step) => (
                        <li key={step.title} className="flex flex-col">
                            <span className="text-meta text-muted-foreground">
                                {step.title}
                            </span>
                            <span className="font-medium tabular-nums">
                                {step.detail}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
            <div className="flex flex-col gap-4">
                <div className="flex flex-1 flex-col gap-1 card p-5">
                    <p className="text-[24px] leading-8 font-semibold tabular-nums">
                        {advanced}
                    </p>
                    <p className="text-meta text-muted-foreground">
                        {advanced === 1 ? 'adelantado' : 'adelantados'}
                    </p>
                </div>
                {open !== null && (
                    <div className="flex flex-1 flex-col items-start gap-2 card p-5">
                        <p className="text-meta">
                            Constancia {open.year} · sin acuse
                        </p>
                        <Button variant="outline" asChild>
                            <Link
                                href={statement.url({
                                    tenant: tenantSlug,
                                    statement: open.id,
                                })}
                            >
                                Ver constancia
                            </Link>
                        </Button>
                        {canManage && (
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        links.url(tenantSlug),
                                        { employees: [personId] },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Enviar para acuse →
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
