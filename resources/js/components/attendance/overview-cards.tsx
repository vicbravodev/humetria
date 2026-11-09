import OverviewCard from '@/components/attendance/overview-card';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overviewQuery } from '@/lib/attendance/overview';
import { index } from '@/routes/tenant/attendance';
import { index as corrections } from '@/routes/tenant/attendance/corrections';
import type { AttendanceOverviewProps, OverviewView } from '@/types';

/**
 * The week at a glance: how many answered it and how, who is near the year's limits, what waits for RH and the centers
 * where nobody can mark yet. Each figure opens who it counts.
 */
export default function OverviewCards({
    week,
    center,
    counts,
    pendingCorrections,
    centersWithoutZones,
}: Pick<
    AttendanceOverviewProps,
    'week' | 'center' | 'counts' | 'pendingCorrections' | 'centersWithoutZones'
>) {
    const tenantSlug = useTenantSlug();
    const tab = (status: OverviewView) =>
        index(tenantSlug, {
            query: overviewQuery({
                q: '',
                status,
                week: week.start,
                center: center?.id ?? '',
            }),
        });

    return (
        <section
            aria-label="La semana por estado"
            className="grid grid-cols-2 gap-3 min-[1240px]:grid-cols-6 md:grid-cols-3"
        >
            <OverviewCard
                label="Al día"
                value={counts.confirmed}
                hint="Confirmaron su semana"
                href={tab('confirmed')}
            />
            <OverviewCard
                label="Objetadas"
                value={counts.disputed}
                hint="Dijeron qué no está bien"
                href={tab('disputed')}
                alert
            />
            <OverviewCard
                label="Sin confirmar"
                value={counts.pending}
                hint={
                    week.over ? 'Aún no responden' : 'La semana sigue en curso'
                }
                href={tab('pending')}
            />
            <OverviewCard
                label="Topes en riesgo"
                value={counts.at_risk}
                hint="Cerca del tope de horas extra o con un límite rebasado"
                href={tab('at_risk')}
                alert
            />
            <OverviewCard
                label="Correcciones por resolver"
                value={pendingCorrections}
                hint="Lo que piden corregir de su registro"
                href={corrections(tenantSlug)}
            />
            <OverviewCard
                label="Centros sin zonas"
                value={centersWithoutZones}
                hint="Ahí nadie puede marcar con su ubicación"
                href="#centros"
                alert
            />
        </section>
    );
}
