import GroupBars from '@/components/results/group-bars';
import { hiddenAreasText } from '@/lib/results';
import type { AreaResults, RiskLevelInfo, RiskResults } from '@/types';

/**
 * Where to act first in the center (or the area chosen): its domains and categories from the worst off, and each
 * area shown.
 */
export default function RiskResultsSections({
    results,
    areas,
    levels,
}: {
    results: RiskResults;
    areas: AreaResults;
    levels: RiskLevelInfo[];
}) {
    const hidden = hiddenAreasText(areas.hidden);

    return (
        <>
            <GroupBars
                heading={<h2 className="text-lg font-semibold">Por dominio</h2>}
                description="De mayor a menor riesgo. La norma pide analizar cada dominio en alto o muy alto y actuar con un Programa de intervención."
                noun="Dominio"
                rows={results.domains}
                levels={levels}
                caption="Dominios por nivel de riesgo"
            />
            <GroupBars
                heading={
                    <h2 className="text-lg font-semibold">Por categoría</h2>
                }
                description="De mayor a menor riesgo."
                noun="Categoría"
                rows={results.categories}
                levels={levels}
                caption="Categorías por nivel de riesgo"
            />
            {areas.visible.length > 0 ? (
                <GroupBars
                    heading={
                        <h2 className="text-lg font-semibold">Por área</h2>
                    }
                    description={`Solo áreas con al menos 5 cuestionarios, y de forma que el resto no se pueda deducir.${hidden === null ? '' : ` ${hidden}.`}`}
                    noun="Área"
                    rows={areas.visible.map((each) => ({
                        key: each.id,
                        label: each.name,
                        answered: each.answered,
                        levels: each.final,
                    }))}
                    levels={levels}
                    caption="Calificación final por área"
                />
            ) : (
                <section className="flex flex-col gap-2 rounded-xl border bg-card p-5 sm:p-6">
                    <h2 className="text-lg font-semibold">Por área</h2>
                    <p className="text-sm text-muted-foreground">
                        Ninguna área se muestra: se necesitan al menos 5
                        cuestionarios por área, y que el resto no se pueda
                        deducir.{hidden === null ? '' : ` ${hidden}.`}
                    </p>
                </section>
            )}
        </>
    );
}
