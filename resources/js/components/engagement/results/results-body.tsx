import DriverList from '@/components/engagement/results/driver-list';
import GroupMap from '@/components/engagement/results/group-map';
import HighlightsCard from '@/components/engagement/results/highlights-card';
import IndexCard from '@/components/engagement/results/index-card';
import LoopActionCard from '@/components/engagement/results/loop-action-card';
import RecommendationCard from '@/components/engagement/results/recommendation-card';
import { resultsReading } from '@/lib/engagement/results';
import { cn } from '@/lib/utils';
import type { EngagementResults } from '@/types';

/**
 * The results of a closed survey (pantalla Clima · Resultados): one sentence that reads them, the engagement as the one
 * protagonist figure beside the recommendation, one list of where to act, strengths and opportunities, and the map by
 * group.
 */
export default function ResultsBody({
    results,
}: {
    results: EngagementResults;
}) {
    const reading = resultsReading(results.index, results.priorities);

    return (
        <>
            {reading !== null && (
                <h2 className="max-w-[760px] text-xl leading-7 font-semibold text-pretty">
                    {reading}
                </h2>
            )}
            <div
                className={cn(
                    'grid gap-4',
                    results.loopAction === null
                        ? 'lg:grid-cols-[1fr_1.8fr]'
                        : 'lg:grid-cols-[1.2fr_1fr_1fr]',
                )}
            >
                <IndexCard
                    index={results.index}
                    delta={results.indexDelta}
                    stay={results.stay?.favorable ?? null}
                    previousName={results.comparison?.name ?? null}
                />
                {results.recommendation !== null && (
                    <RecommendationCard
                        recommendation={results.recommendation}
                    />
                )}
                {results.loopAction !== null && (
                    <LoopActionCard loopAction={results.loopAction} />
                )}
            </div>
            <DriverList
                drivers={results.drivers}
                priorities={results.priorities}
                reference={results.index}
            />
            {results.highlights !== null && (
                <HighlightsCard
                    strengths={results.highlights.strengths}
                    opportunities={results.highlights.opportunities}
                    reference={results.index}
                />
            )}
            <GroupMap results={results} />
        </>
    );
}
