import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import DriverTrendList from '@/components/engagement/pulse/driver-trend-list';
import TrendCard from '@/components/engagement/pulse/trend-card';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/pulse';
import type { TrendSeries } from '@/types';

/** How the company moves from survey to survey, as a whole: no groups, only what each wave may show. */
export default function EngagementTrend({
    hasWaves,
    series,
    canManage,
}: {
    hasWaves: boolean;
    series: TrendSeries[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Pulso', href: index(tenantSlug) },
            { title: 'Tendencia' },
        ],
    });

    return (
        <>
            <Head title="Tendencia" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Tendencia"
                    description="Cómo cambia la empresa de una encuesta a otra. Una flecha aparece solo cuando las dos encuestas tienen 30 respuestas o más y la pregunta se hizo con las mismas palabras."
                />
                {hasWaves ? (
                    <>
                        <div className="grid gap-4 lg:grid-cols-3">
                            {series.slice(0, 3).map((one) => (
                                <TrendCard key={one.key} series={one} />
                            ))}
                        </div>
                        <DriverTrendList series={series.slice(3)} />
                    </>
                ) : (
                    <EmptyState
                        icon={Activity}
                        title="Todavía no hay con qué comparar"
                        {...(canManage
                            ? {
                                  action: (
                                      <Button asChild>
                                          <Link href={index(tenantSlug)}>
                                              Programa tu primer pulso
                                          </Link>
                                      </Button>
                                  ),
                              }
                            : {})}
                    >
                        La tendencia aparece cuando se cierra la primera
                        encuesta, y se vuelve útil desde la segunda.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
