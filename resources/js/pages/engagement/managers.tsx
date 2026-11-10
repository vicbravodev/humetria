import { Head, setLayoutProps } from '@inertiajs/react';
import { Hourglass, Users } from 'lucide-react';
import ManagerTable from '@/components/engagement/results/manager-table';
import EmptyState from '@/components/humetria/empty-state';
import Notice from '@/components/humetria/notice';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ManagerResults, ManagerRow, Paginated } from '@/types';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';

/** The managers whose group of people is shown, by name (pantallas: Empleados, Eval-Cerrada). Never a ranking. */
export default function Managers({
    survey,
    available,
    managers,
    hasHidden,
}: ManagerResults & { managers: Paginated<ManagerRow> }) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima' },
            {
                title: survey.name,
                href: routes.results.url([tenantSlug, survey.id]),
            },
            { title: 'Por jefe' },
        ],
    });

    return (
        <>
            <Head title={`Por jefe · ${survey.name}`} />
            <div className="flex w-full flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Resultados de clima"
                    title="Retroalimentación por jefe"
                    description="Cada jefe ve solo lo que contestó su grupo, sin las respuestas de nadie. Aquí no hay ranking: la lista va por nombre."
                />
                {hasHidden && (
                    <Notice>
                        <strong>Algunos jefes no aparecen.</strong> Su grupo
                        tuvo menos respuestas que el mínimo, o mostrarlo
                        permitiría deducir quién contestó.
                    </Notice>
                )}
                {!available ? (
                    <EmptyState
                        icon={Hourglass}
                        title="La retroalimentación estará disponible al cerrar la encuesta"
                    >
                        Mientras sigue abierta, cada respuesta nueva diría qué
                        contestó quien acaba de contestar.
                    </EmptyState>
                ) : managers.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Ningún jefe tiene un grupo que se pueda mostrar"
                    >
                        Un grupo se muestra con al menos 5 respuestas. Los
                        resultados de toda la empresa siguen en la pantalla de
                        resultados.
                    </EmptyState>
                ) : (
                    <>
                        <ManagerTable
                            tenantSlug={tenantSlug}
                            surveyId={survey.id}
                            managers={managers.data}
                        />
                        <Pagination page={managers} label="Jefes" />
                    </>
                )}
            </div>
        </>
    );
}
