import { Head, setLayoutProps } from '@inertiajs/react';
import { Briefcase, MapPin, MonitorSmartphone, Users } from 'lucide-react';
import CenterMode from '@/components/attendance/center-mode';
import CenterScreens from '@/components/attendance/center-screens';
import CenterZones from '@/components/attendance/center-zones';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { centerState } from '@/lib/attendance/center-state';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceCenterPageProps } from '@/types';

/** A center of the registro (pantalla Eval-Activa: header with its facts, then its parts): its zones and its QR screens. */
export default function AttendanceCenter({
    center,
    centerAddress,
    people,
    zones,
    kiosks,
    canManage,
    qrSeconds,
    requiresQr,
    screens,
    tolerance,
    mode,
}: AttendanceCenterPageProps) {
    const tenantSlug = useTenantSlug();
    const state = centerState(
        { zones: zones.total, screens, mode },
        requiresQr,
    );
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: center.name },
        ],
    });

    return (
        <>
            <Head title={`${center.name} · Asistencia`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Centro de trabajo"
                    title={center.name}
                    status={
                        <Badge variant={state.variant}>{state.label}</Badge>
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
                        {
                            icon: MonitorSmartphone,
                            text:
                                screens === 1
                                    ? '1 pantalla activa'
                                    : `${screens} pantallas activas`,
                        },
                    ]}
                />
                {!canManage && <ReadOnlyNotice />}
                <CenterMode
                    centerId={center.id}
                    mode={mode}
                    canManage={canManage}
                />
                <CenterZones
                    zones={zones}
                    centerId={center.id}
                    centerAddress={centerAddress}
                    canManage={canManage}
                    tolerance={tolerance}
                />
                <CenterScreens
                    kiosks={kiosks}
                    centerId={center.id}
                    canManage={canManage}
                    qrSeconds={qrSeconds}
                    active={screens}
                />
            </div>
        </>
    );
}
