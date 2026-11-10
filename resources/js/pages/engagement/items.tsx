import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, Hourglass } from 'lucide-react';
import DriverSection from '@/components/engagement/results/driver-section';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { EngagementItems } from '@/types';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';

export default function Items({
    survey,
    available,
    sections,
}: EngagementItems) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clima' },
            {
                title: survey.name,
                href: routes.results.url([tenantSlug, survey.id]),
            },
            { title: 'Por pregunta' },
        ],
    });

    return (
        <>
            <Head title={`Resultados por pregunta · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Resultados de clima"
                    title="Resultados por pregunta"
                    description="Cada pregunta con sus respuestas favorables y cómo se reparten. Las preguntas con menos de 5 respuestas no muestran cifras."
                >
                    <Button asChild variant="outline">
                        <Link
                            href={routes.results.url([tenantSlug, survey.id])}
                        >
                            <ArrowLeft aria-hidden />
                            Resumen
                        </Link>
                    </Button>
                </PageHeader>
                {available ? (
                    sections.map((section) => (
                        <DriverSection key={section.key} section={section} />
                    ))
                ) : (
                    <EmptyState
                        icon={Hourglass}
                        title="Los resultados estarán disponibles al cerrar la encuesta"
                    >
                        Mientras sigue abierta, cada respuesta nueva diría qué
                        contestó quien acaba de contestar.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
