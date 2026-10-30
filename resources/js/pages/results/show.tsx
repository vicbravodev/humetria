import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PageHeader from '@/components/page-header';
import OpenEvaluationNotice from '@/components/results/open-evaluation-notice';
import ResultsBody from '@/components/results/results-body';
import ResultsFacts from '@/components/results/results-facts';
import TraumaSummary from '@/components/results/trauma-summary';
import {
    report,
    results as resultsRoute,
    show,
} from '@/routes/tenant/campaigns';
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
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title={`Resultados · ${campaign.workCenter}`} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <Link
                    href={show([tenantSlug, campaign.id])}
                    className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {campaign.kindLabel}
                </Link>
                <PageHeader
                    caption={`Resultados · ${guide?.title ?? campaign.kindLabel}`}
                    title={campaign.workCenter}
                />
                <ResultsFacts
                    campaign={campaign}
                    participation={participation}
                />
                <OpenEvaluationNotice guide={guide} closed={campaign.closed} />
                {results !== null && areas !== null && items !== null && (
                    <ResultsBody
                        url={resultsRoute.url([tenantSlug, campaign.id])}
                        reportUrl={report.url([tenantSlug, campaign.id])}
                        results={results}
                        areas={areas}
                        items={items}
                        area={filters.area}
                        levels={levels}
                    />
                )}
                {trauma !== null && (
                    <TraumaSummary
                        url={[tenantSlug, campaign.id]}
                        counts={trauma}
                    />
                )}
            </div>
        </>
    );
}
