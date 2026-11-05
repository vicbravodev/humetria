import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { reportsLine } from '@/lib/seating/route';
import type { LateReports } from '@/lib/seating/route';

/** «Antes de seguir» (board V2-Ruta-Silla): the damaged seats people reported and nobody resolved in time. */
export default function RouteBefore({ reports }: { reports: LateReports }) {
    return (
        <section
            aria-labelledby="before"
            className="flex flex-col gap-3 rounded-2xl bg-risk-alto-bg p-[22px] text-risk-alto-fg"
        >
            <h2 id="before" className="text-sm font-semibold">
                Antes de seguir
            </h2>
            <p className="text-foreground">{reportsLine(reports)}</p>
            <Button asChild variant="outline" size="sm" className="self-start">
                <Link href={reports.href}>
                    {reports.count === 1
                        ? 'Revisar el aviso'
                        : 'Revisar los avisos'}
                </Link>
            </Button>
        </section>
    );
}
