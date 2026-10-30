import GroupBars from '@/components/results/group-bars';
import RiskSummary from '@/components/results/risk-summary';
import { hiddenAreasText } from '@/lib/results';
import type { AreaResults, RiskLevelInfo, RiskResults } from '@/types';

/**
 * The center (or the area chosen) by risk level: its final score read in one sentence and one bar, then where to act
 * first: its domains and categories from the worst off, and each area shown.
 */
export default function RiskResultsSections({
    results,
    areas,
    levels,
    area,
}: {
    results: RiskResults;
    areas: AreaResults;
    levels: RiskLevelInfo[];
    /** The area the results are narrowed to, by name; null for the whole center. */
    area: string | null;
}) {
    const hidden = hiddenAreasText(areas.hidden);
    const scope = area ?? 'todo el centro';

    return (
        <>
            <section className="grid gap-4">
                <div className="grid gap-1">
                    <h2 className="text-xl font-semibold">
                        Calificación final
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Cuántos de los {results.answered} cuestionarios de{' '}
                        {scope} quedan en cada nivel. Cada cuestionario se
                        califica por sí solo; no se promedian.
                    </p>
                </div>
                <RiskSummary results={results} levels={levels} />
            </section>
            <GroupBars
                heading={<h2 className="text-xl font-semibold">Por dominio</h2>}
                description="De mayor a menor riesgo. La norma pide analizar cada dominio en alto o muy alto y actuar con un Programa de intervención."
                noun="Dominio"
                rows={results.domains}
                levels={levels}
                caption="Dominios por nivel de riesgo"
            />
            <GroupBars
                heading={
                    <h2 className="text-xl font-semibold">Por categoría</h2>
                }
                noun="Categoría"
                rows={results.categories}
                levels={levels}
                caption="Categorías por nivel de riesgo"
            />
            {areas.visible.length > 0 ? (
                <GroupBars
                    heading={
                        <h2 className="text-xl font-semibold">Por área</h2>
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
                <section className="grid gap-1">
                    <h2 className="text-xl font-semibold">Por área</h2>
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
