import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import RegisterAspirantForm from '@/components/telework/register-aspirant-form';
import TeleworkerDetail from '@/components/telework/teleworker-detail';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/telework/committee';
import type { TeleworkerSummary } from '@/types';
import { teleworkCrumbs } from '@/lib/telework/crumbs';

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
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(tenantSlug, workCenter, {
            title: teleworker?.name ?? 'Agregar persona',
        }),
    });

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
                    <TeleworkerDetail teleworker={teleworker} states={states} />
                ) : hasChecklist ? (
                    <RegisterAspirantForm
                        workCenterId={workCenter.id}
                        states={states}
                    />
                ) : (
                    <EmptyState
                        icon={ClipboardList}
                        title="La Comisión aún no publica la lista"
                        action={
                            <Button asChild variant="outline">
                                <Link
                                    href={show({
                                        tenant: tenantSlug,
                                        workCenter: workCenter.id,
                                    })}
                                >
                                    Ir a la Comisión
                                </Link>
                            </Button>
                        }
                    >
                        Antes de registrar a alguien, la Comisión de este centro
                        debe publicar su lista de verificación.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
