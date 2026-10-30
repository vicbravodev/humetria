import { ChartColumn } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import AreaFilter from '@/components/results/area-filter';
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
 * The Guía II or III results of a closed evaluation, or why there are none to show: fewer than five questionnaires.
 */
export default function ResultsBody({
    url,
    results,
    areas,
    items,
    area,
    levels,
}: {
    url: string;
    results: RiskResults;
    areas: AreaResults;
    items: ItemSection[];
    area: { value: string; label: string } | null;
    levels: RiskLevelInfo[];
}) {
    if (!results.visible) {
        return (
            <EmptyState icon={ChartColumn} title="Sin resultados que mostrar">
                Hay {results.answered} cuestionarios y se necesitan al menos 5
                para mostrar resultados sin revelar respuestas.
            </EmptyState>
        );
    }

    return (
        <>
            {areas.visible.length > 0 && (
                <AreaFilter
                    url={url}
                    areas={areas.visible}
                    selected={area?.value ?? null}
                />
            )}
            <RiskResultsSections
                results={results}
                areas={areas}
                levels={levels}
                scope={area?.label ?? 'todo el centro'}
            />
            <ItemsResults sections={items} />
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">
                    Qué pide la norma en cada nivel
                </h2>
                <RiskScale levels={levels} />
            </section>
        </>
    );
}
