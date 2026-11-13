import { Head } from '@inertiajs/react';
import PageHeader from '@/components/humetria/page-header';
import OverviewBreakdown from '@/components/tenancy/overview-breakdown';
import OverviewCard from '@/components/tenancy/overview-card';
import { DEMO_VIEWS, demoQuery } from '@/lib/demo-requests';
import { demosLine, pesos, quietLine } from '@/lib/overview';
import { companiesQuery } from '@/lib/tenant-status';
import { index as accounts } from '@/routes/billing/accounts';
import { index as companies } from '@/routes/companies';
import { index as demoRequests } from '@/routes/demo-requests';
import type { PlatformOverviewData, TenantView } from '@/types';

const STATUSES: { value: TenantView; label: string }[] = [
    { value: 'active', label: 'Activas' },
    { value: 'trial', label: 'En prueba' },
    { value: 'expired', label: 'Vencidas' },
    { value: 'read_only', label: 'Solo lectura' },
    { value: 'suspended', label: 'Suspendidas' },
    { value: 'archived', label: 'Archivadas' },
];

/** «Resumen»: the platform in four figures, each one a way into its list. */
export default function Overview({
    companies: counts,
    revenueCents,
    demos,
    inactive,
}: PlatformOverviewData) {
    return (
        <>
            <Head title="Resumen" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Resumen"
                    description="Cómo va la plataforma hoy: empresas, ingreso, demos y quién no ha vuelto."
                />
                <div className="grid gap-4 md:grid-cols-2">
                    <OverviewCard
                        title="Empresas"
                        figure={String(counts.all)}
                        caption="Dadas de alta en la plataforma."
                        href={companies()}
                        action="Ver empresas"
                    >
                        <OverviewBreakdown
                            label="Empresas por estado"
                            rows={STATUSES.map((status) => ({
                                label: status.label,
                                count: counts[status.value],
                                href: companies.url({
                                    query: companiesQuery({
                                        q: '',
                                        status: status.value,
                                    }),
                                }),
                            }))}
                        />
                    </OverviewCard>
                    <OverviewCard
                        title="Ingreso mensual contratado"
                        figure={pesos(revenueCents)}
                        caption="Antes de IVA, de las suscripciones que pagan hoy."
                        href={accounts()}
                        action="Ver cuentas"
                    />
                    <OverviewCard
                        title="Solicitudes de demo"
                        figure={String(demos.all)}
                        caption={demosLine(demos.new)}
                        href={demoRequests()}
                        action="Ver solicitudes"
                    >
                        <OverviewBreakdown
                            label="Solicitudes por estado"
                            rows={DEMO_VIEWS.filter(
                                (view) => view.value !== 'all',
                            ).map((view) => ({
                                label: view.label,
                                count: demos[view.value],
                                href: demoRequests.url({
                                    query: demoQuery(view.value),
                                }),
                            }))}
                        />
                    </OverviewCard>
                    <OverviewCard
                        title="Sin entrar en 30 días"
                        figure={String(inactive)}
                        caption={quietLine(inactive)}
                        href={companies()}
                        action="Ver empresas"
                    />
                </div>
            </div>
        </>
    );
}
