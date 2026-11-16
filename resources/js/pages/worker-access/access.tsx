import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import OneTimeLink from '@/components/humetria/one-time-link';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import AccessPeople from '@/components/worker-access/access-people';
import InviteCenterButton from '@/components/worker-access/invite-center-button';
import type { WorkerAccessPageProps } from '@/types';

/**
 * «Accesos» (pantalla Empleados: header, search, center and tabs with counts, the people page by page): each person's
 * keys, their invitation, reissuing a lost phone and revoking a key.
 */
export default function WorkerAccess(props: WorkerAccessPageProps) {
    const link = usePage().flash.accessLink;
    setLayoutProps({ breadcrumbs: [{ title: 'Accesos' }] });

    return (
        <>
            <Head title="Accesos" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={props.center?.name ?? 'Todos tus centros'}
                    title="Accesos"
                    description="Cada persona entra a su portal y marca con la llave de acceso de su teléfono. Invítala para que cree la suya y reemite su acceso si pierde el teléfono."
                >
                    {props.canManage && props.center !== null && (
                        <InviteCenterButton
                            center={props.center}
                            waiting={
                                props.counts.invited + props.counts.not_invited
                            }
                            linkHours={props.linkHours}
                        />
                    )}
                </PageHeader>
                {!props.canManage && <ReadOnlyNotice />}
                {link !== undefined && (
                    <OneTimeLink
                        id="access-link"
                        label={`Enlace de acceso de ${link.name}`}
                        link={link.url}
                    >
                        Entrégaselo en persona o por un medio que solo use esa
                        persona: con él entra y crea su llave. Funciona una sola
                        vez, vence en {props.linkHours} h y no se vuelve a
                        mostrar.
                    </OneTimeLink>
                )}
                <AccessPeople {...props} />
            </div>
        </>
    );
}
