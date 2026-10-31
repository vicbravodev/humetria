import { Link } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import DashboardCycleRow from '@/components/tenancy/dashboard-cycle-row';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { CycleRow, Paginated } from '@/types';

/** "Ciclo de cumplimiento" (pantalla Panel): each work center in use and how far it is in its two years (num. 7.9). */
export default function DashboardCycle({
    tenantSlug,
    cycle,
}: {
    tenantSlug: string;
    cycle: Paginated<CycleRow>;
}) {
    return (
        <section
            aria-labelledby="cycle"
            className="overflow-hidden rounded-xl border bg-card"
        >
            <div className="flex flex-wrap items-center justify-between gap-4 px-5 pt-5 pb-4">
                <div className="flex flex-col gap-0.5">
                    <h2
                        id="cycle"
                        className="text-lg leading-[26px] font-semibold"
                    >
                        Ciclo de cumplimiento
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        La NOM-035 pide evaluar cada centro de trabajo al menos
                        cada 2 años.
                    </p>
                </div>
                <Link
                    href={workCenters(tenantSlug)}
                    className="text-sm font-medium text-primary underline-offset-[3px] hover:underline"
                >
                    Ver centros de trabajo
                </Link>
            </div>
            <ul>
                {cycle.data.map((row) => (
                    <DashboardCycleRow key={row.id} row={row} />
                ))}
            </ul>
            {cycle.last_page > 1 && (
                <div className="border-t px-5 py-3">
                    <ConsolePagination
                        page={cycle}
                        label="Páginas de centros de trabajo"
                        previous="Anteriores"
                        next="Siguientes"
                        pinned={false}
                    />
                </div>
            )}
        </section>
    );
}
