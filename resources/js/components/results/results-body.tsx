import ItemsResults from '@/components/results/items-results';
import RiskResultsSections from '@/components/results/risk-results';
import RiskScale from '@/components/results/risk-scale';
import type {
    AreaResults,
    ItemSection,
    RiskLevelInfo,
    RiskResults,
} from '@/types';

/**
 * The detail of the Guía II or III results of a closed evaluation, under its final score: where to act first, the
 * worst-answered questions and what the norm asks at each level. Nothing under the minimum of five questionnaires.
 */
export default function ResultsBody({
    results,
    areas,
    items,
    levels,
}: {
    /** Null, with the areas and the questions, while the evaluation runs or when it applies no Guía II or III. */
    results: RiskResults | null;
    areas: AreaResults | null;
    items: ItemSection[] | null;
    levels: RiskLevelInfo[];
}) {
    if (
        results === null ||
        areas === null ||
        items === null ||
        !results.visible
    ) {
        return null;
    }

    return (
        <>
            <RiskResultsSections
                results={results}
                areas={areas}
                levels={levels}
            />
            <ItemsResults sections={items} />
            <section className="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:p-6">
                <h2 className="text-lg font-semibold">
                    Qué pide la norma en cada nivel
                </h2>
                <RiskScale levels={levels} />
            </section>
        </>
    );
}
