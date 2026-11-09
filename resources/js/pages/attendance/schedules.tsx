import { Head, setLayoutProps } from '@inertiajs/react';
import SchedulePeople from '@/components/attendance/schedule-people';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { duration } from '@/lib/attendance/week-text';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceSchedulesProps } from '@/types';

/**
 * The people's agreed schedules (pantalla Empleados: header, search, center and tabs with counts, the people page by page):
 * with them the hours of each day are split into ordinary and overtime.
 */
export default function AttendanceSchedules(props: AttendanceSchedulesProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: 'Horarios' },
        ],
    });

    return (
        <>
            <Head title="Horarios · Asistencia" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={props.center?.name ?? 'Todos tus centros'}
                    title="Horarios"
                    description={`El horario que cada persona acordó: lo que trabaje de más cuenta como tiempo extra. En ${props.weeklyMaximum.year} la jornada ordinaria es de hasta ${duration(props.weeklyMaximum.minutes)} a la semana.`}
                />
                <SchedulePeople {...props} />
            </div>
        </>
    );
}
