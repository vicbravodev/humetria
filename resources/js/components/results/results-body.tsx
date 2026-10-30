import { Link } from '@inertiajs/react';
import { ChartColumn, FileText } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import AreaFilter from '@/components/results/area-filter';
import ItemsResults from '@/components/results/items-results';
import RiskResultsSections from '@/components/results/risk-results';
import RiskScale from '@/components/results/risk-scale';
import { Button } from '@/components/ui/button';
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
    reportUrl,
    results,
    areas,
    items,
    area,
    levels,
}: {
    url: string;
    reportUrl: string;
    results: RiskResults;
    areas: AreaResults;
    items: ItemSection[];
    area: { value: string; label: string } | null;
    levels: RiskLevelInfo[];
}) {
    const report = (
        <Button asChild variant="outline" className="w-fit">
            <Link href={reportUrl}>
                <FileText aria-hidden="true" />
                Informe del numeral 7.7
            </Link>
        </Button>
    );

    if (!results.visible) {
        return (
            <>
                {report}
                <EmptyState
                    icon={ChartColumn}
                    title="Sin resultados que mostrar"
                >
                    Hay {results.answered} cuestionarios y se necesitan al menos
                    5 para mostrar resultados sin revelar respuestas.
                </EmptyState>
            </>
        );
    }

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-4">
                {areas.visible.length > 0 ? (
                    <AreaFilter
                        url={url}
                        areas={areas.visible}
                        selected={area?.value ?? null}
                    />
                ) : (
                    <span />
                )}
                {report}
            </div>
            <RiskResultsSections
                results={results}
                areas={areas}
                levels={levels}
                area={area?.label ?? null}
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
