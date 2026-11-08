import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import DevicePeople from '@/components/attendance/device-people';
import EnrollAllButton from '@/components/attendance/enroll-all-button';
import OneTimeLink from '@/components/attendance/one-time-link';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceDevicesProps } from '@/types';

/**
 * The people's linked devices (pantalla Empleados: header, search, center and tabs with counts, the people page by
 * page): each person's phone, the link to link one, and revoking a lost one.
 */
export default function AttendanceDevices(props: AttendanceDevicesProps) {
    const tenantSlug = useTenantSlug();
    const link = usePage().flash.enrollmentLink;
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: 'Dispositivos' },
        ],
    });

    return (
        <>
            <Head title="Dispositivos · Asistencia" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={props.center?.name ?? 'Todos tus centros'}
                    title="Dispositivos"
                    description="Cada persona marca con la llave de acceso de su teléfono. Envíale su enlace de alta y revoca el teléfono que pierda o cambie."
                >
                    {props.canManage && props.center !== null && (
                        <EnrollAllButton
                            center={props.center}
                            waiting={
                                props.counts.unlinked + props.counts.revoked
                            }
                        />
                    )}
                </PageHeader>
                {link !== undefined && (
                    <OneTimeLink
                        id="enrollment-link"
                        label={`Enlace de alta de ${link.name}`}
                        link={link.url}
                    >
                        Entrégaselo en persona o por un medio que solo use esa
                        persona: con él vincula su teléfono. Funciona una sola
                        vez, vence en 48 h y no se vuelve a mostrar.
                    </OneTimeLink>
                )}
                <DevicePeople {...props} />
            </div>
        </>
    );
}
