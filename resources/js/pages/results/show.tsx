import { Head, setLayoutProps } from '@inertiajs/react';
import AreaFilter from '@/components/humetria/area-filter';
import ResultsBody from '@/components/results/results-body';
import ResultsHeader from '@/components/results/results-header';
import ResultsOverview from '@/components/results/results-overview';
import TraumaSummary from '@/components/results/trauma-summary';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { resultsTrail } from '@/lib/results';
import { report, results as resultsRoute } from '@/routes/tenant/campaigns';
import type {
    AreaResults,
    ItemSection,
    Participation,
    ResultsCampaign,
    RiskLevelInfo,
    RiskResults,
    ScreeningCounts,
} from '@/types';

export default function Results({
    campaign,
    guide,
    participation,
    results,
    areas,
    items,
    filters,
    levels,
    trauma,
}: {
    campaign: ResultsCampaign;
    guide: { key: string; title: string } | null;
    participation: Participation | null;
    results: RiskResults | null;
    areas: AreaResults | null;
    items: ItemSection[] | null;
    filters: { area: { value: string; label: string } | null };
    levels: RiskLevelInfo[];
    trauma: ScreeningCounts | null;
}) {
    const tenantSlug = useTenantSlug();
    const area = filters.area;
    setLayoutProps({
        breadcrumbs: resultsTrail(tenantSlug, campaign, 'Resultados'),
    });

    return (
        <>
            <Head title={`Resultados · ${campaign.workCenter}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ResultsHeader
                    campaign={campaign}
                    reportUrl={
                        results === null
                            ? null
                            : report.url([tenantSlug, campaign.id])
                    }
                />
                <AreaFilter
                    url={resultsRoute.url([tenantSlug, campaign.id])}
                    areas={areas?.visible ?? []}
                    selected={area}
                />
                <ResultsOverview
                    guide={guide}
                    participation={participation}
                    results={results}
                    area={area}
                    levels={levels}
                />
                {trauma !== null && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <TraumaSummary
                            url={[tenantSlug, campaign.id]}
                            counts={trauma}
                        />
                    </div>
                )}
                <ResultsBody
                    results={results}
                    areas={areas}
                    items={items}
                    levels={levels}
                />
            </div>
        </>
    );
}
