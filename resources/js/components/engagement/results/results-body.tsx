import CutSection from '@/components/engagement/results/cut-section';
import DriverList from '@/components/engagement/results/driver-list';
import HighlightsCard from '@/components/engagement/results/highlights-card';
import IndexCard from '@/components/engagement/results/index-card';
import PriorityList from '@/components/engagement/results/priority-list';
import RecommendationCard from '@/components/engagement/results/recommendation-card';
import type { EngagementParticipation, EngagementResults } from '@/types';

/** The results of a closed survey: the company as a whole, then by group. */
export default function ResultsBody({
    tenantSlug,
    results,
    participation,
}: {
    tenantSlug: string;
    results: EngagementResults;
    participation: EngagementParticipation;
}) {
    return (
        <>
            <IndexCard
                index={results.index}
                delta={results.indexDelta}
                stay={results.stay?.favorable ?? null}
                participation={participation}
                previousName={results.comparison?.name ?? null}
            />
            {results.recommendation !== null && (
                <RecommendationCard recommendation={results.recommendation} />
            )}
            <PriorityList priorities={results.priorities} />
            <DriverList drivers={results.drivers} />
            {results.highlights !== null && (
                <HighlightsCard
                    strengths={results.highlights.strengths}
                    opportunities={results.highlights.opportunities}
                />
            )}
            <CutSection tenantSlug={tenantSlug} results={results} />
        </>
    );
}
