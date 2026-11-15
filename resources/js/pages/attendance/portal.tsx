import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Clock } from 'lucide-react';
import PersonFrame from '@/components/attendance/person-frame';
import PortalDevices from '@/components/attendance/portal-devices';
import type { PortalDevice } from '@/components/attendance/portal-devices';
import PortalLinkCard from '@/components/attendance/portal-link-card';

/**
 * «Mi jornada» in the person's portal, in usted: the way to mark and to see their week, and the phones that mark for them.
 * Marking and the week are signed by the key of the phone, never by the portal link.
 */
export default function AttendancePortal({
    company,
    name,
    devices,
    markUrl,
    weekUrl,
    enrollUrl,
    homeUrl,
}: {
    token: string;
    company: string;
    name: string;
    devices: PortalDevice[];
    markUrl: string;
    weekUrl: string;
    enrollUrl: string;
    homeUrl: string;
}) {
    return (
        <PersonFrame company={company}>
            <Head title="Mi jornada" />
            <div className="flex w-full max-w-md flex-col gap-5">
                <Link
                    href={homeUrl}
                    className="touch-hit flex w-fit items-center gap-1.5 text-base font-medium text-primary"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Volver a mi portal
                </Link>
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Mi jornada
                    </h1>
                    <p className="text-muted-foreground">
                        {name}, desde aquí marca su entrada y su salida y ve su
                        semana.
                    </p>
                </div>
                <PortalLinkCard href={markUrl} icon={Clock} title="Marcar">
                    Su entrada, sus pausas y su salida.
                </PortalLinkCard>
                <PortalLinkCard
                    href={weekUrl}
                    icon={CalendarDays}
                    title="Mi semana"
                >
                    Sus marcas y sus horas; confirme su semana o pida una
                    corrección.
                </PortalLinkCard>
                <PortalDevices devices={devices} enrollUrl={enrollUrl} />
            </div>
        </PersonFrame>
    );
}
