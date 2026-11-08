import { Head, setLayoutProps } from '@inertiajs/react';
import { Briefcase, MapPin, Users } from 'lucide-react';
import CenterZones from '@/components/attendance/center-zones';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceCenterProps } from '@/types';

/** A center of the registro (pantalla Eval-Activa: header with its facts, then its parts): its zones. */
export default function AttendanceCenter({
    center,
    people,
    zones,
    canManage,
}: AttendanceCenterProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: center.name },
        ],
    });

    return (
        <>
            <Head title={`${center.name} · Asistencia`} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Centro de trabajo"
                    title={center.name}
                    status={
                        zones.total === 0 ? (
                            <Badge variant="warning">Sin zonas</Badge>
                        ) : (
                            <Badge variant="success">Listo para marcar</Badge>
                        )
                    }
                    facts={[
                        { icon: Briefcase, text: center.mainActivity },
                        {
                            icon: Users,
                            text:
                                people === 1
                                    ? '1 persona'
                                    : `${people} personas`,
                        },
                        {
                            icon: MapPin,
                            text:
                                zones.total === 1
                                    ? '1 zona'
                                    : `${zones.total} zonas`,
                        },
                    ]}
                />
                <CenterZones
                    zones={zones}
                    centerId={center.id}
                    canManage={canManage}
                />
            </div>
        </>
    );
}
