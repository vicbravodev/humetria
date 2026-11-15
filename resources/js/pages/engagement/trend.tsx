import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarCheck, Lock } from 'lucide-react';
import TrendBody from '@/components/engagement/pulse/trend-body';
import TrendWaiting from '@/components/engagement/pulse/trend-waiting';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { wavesSpan } from '@/lib/engagement/sparkline';
import { engagementTrail } from '@/lib/engagement/trail';
import { index, results } from '@/routes/tenant/pulse';
import type { PageFact, TrendSeries, TrendWave } from '@/types';

/** How the company moves from survey to survey, as a whole: no groups, only what each wave may show. */
export default function EngagementTrend({
    series,
    waves,
    latestPulseId,
    nextPulseOn,
    canManage,
}: {
    hasWaves: boolean;
    series: TrendSeries[];
    waves: TrendWave[];
    latestPulseId: string | null;
    nextPulseOn: string | null;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const span = wavesSpan(waves);
    const facts: PageFact[] = [
        ...(span === null ? [] : [{ icon: CalendarCheck, text: span }]),
        {
            icon: Lock,
            text: 'Una flecha solo con 30 respuestas o más en las dos olas y la misma pregunta',
        },
    ];

    setLayoutProps({
        breadcrumbs: engagementTrail('Pulso', index.url(tenantSlug), {
            title: 'Tendencia',
        }),
    });

    return (
        <>
            <Head title="Tendencia" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader title="Tendencia" facts={facts}>
                    {latestPulseId !== null && (
                        <Button asChild variant="outline">
                            <Link
                                href={results.url([tenantSlug, latestPulseId])}
                            >
                                Ver el último pulso
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {waves.length > 1 ? (
                    <TrendBody series={series} waves={waves} />
                ) : (
                    <TrendWaiting
                        waves={waves.length}
                        nextPulseOn={nextPulseOn}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
