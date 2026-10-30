import DistributionTable from '@/components/results/distribution-table';
import { hiddenAreasText } from '@/lib/results';
import type { AreaResults, RiskLevelInfo, RiskResults } from '@/types';

/**
 * The center (or the area chosen) by risk level: its final score, categories and domains; then each area shown.
 */
export default function RiskResultsSections({
    results,
    areas,
    levels,
    scope,
}: {
    results: RiskResults;
    areas: AreaResults;
    levels: RiskLevelInfo[];
    scope: string;
}) {
    const hidden = hiddenAreasText(areas.hidden);

    return (
        <>
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">Calificación final</h2>
                <p className="text-sm text-muted-foreground">
                    Cuántos de los {results.answered} cuestionarios de {scope}{' '}
                    quedan en cada nivel. Cada cuestionario se califica por sí
                    solo; no se promedian.
                </p>
                <DistributionTable
                    caption="Calificación final por nivel de riesgo"
                    heading="Grupo"
                    rows={[
                        { key: 'final', label: scope, levels: results.final },
                    ]}
                    levels={levels}
                />
            </section>
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">Por categoría</h2>
                <DistributionTable
                    caption="Categorías por nivel de riesgo"
                    heading="Categoría"
                    rows={results.categories}
                    levels={levels}
                />
            </section>
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">Por dominio</h2>
                <DistributionTable
                    caption="Dominios por nivel de riesgo"
                    heading="Dominio"
                    rows={results.domains}
                    levels={levels}
                />
            </section>
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">Por área</h2>
                <p className="text-sm text-muted-foreground">
                    Solo áreas con al menos 5 cuestionarios, y de forma que el
                    resto no se pueda deducir.
                    {hidden === null ? '' : ` ${hidden}.`}
                </p>
                {areas.visible.length > 0 && (
                    <DistributionTable
                        caption="Calificación final por área"
                        heading="Área"
                        rows={areas.visible.map((area) => ({
                            key: area.id,
                            label: area.name,
                            answered: area.answered,
                            levels: area.final,
                        }))}
                        levels={levels}
                    />
                )}
            </section>
        </>
    );
}
