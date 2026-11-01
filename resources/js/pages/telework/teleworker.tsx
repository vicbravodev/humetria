import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import RegisterAspirantForm from '@/components/telework/register-aspirant-form';
import TeleworkerDetail from '@/components/telework/teleworker-detail';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/tenant/telework/committee';
import type { TeleworkerSummary } from '@/types';

export default function Teleworker({
    workCenter,
    hasChecklist,
    states,
    teleworker,
}: {
    workCenter: { id: string; name: string };
    hasChecklist: boolean;
    states: Record<string, string>;
    teleworker: TeleworkerSummary | null;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={teleworker?.name ?? 'Agregar persona'} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={workCenter.name}
                    title={teleworker?.name ?? 'Agregar persona'}
                    description={
                        teleworker === null
                            ? 'Elige a quien va a teletrabajar y dónde. Recibirá por correo el enlace a su portal.'
                            : undefined
                    }
                />
                {teleworker !== null ? (
                    <TeleworkerDetail
                        tenantSlug={tenantSlug}
                        teleworker={teleworker}
                        states={states}
                    />
                ) : hasChecklist ? (
                    <RegisterAspirantForm
                        tenantSlug={tenantSlug}
                        workCenterId={workCenter.id}
                        states={states}
                    />
                ) : (
                    <EmptyState
                        icon={ClipboardList}
                        title="La Comisión aún no publica la lista"
                    >
                        Antes de registrar a alguien, la Comisión de este centro
                        debe publicar su lista de verificación.
                        <Button asChild variant="outline" className="mt-4">
                            <Link
                                href={show({
                                    tenant: tenantSlug,
                                    workCenter: workCenter.id,
                                })}
                            >
                                Ir a la Comisión
                            </Link>
                        </Button>
                    </EmptyState>
                )}
            </div>
        </>
    );
}

Teleworker.layout = {
    breadcrumbs: [{ title: 'NOM-037' }, { title: 'Personas' }],
};
