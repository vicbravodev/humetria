import DistributionTable from '@/components/results/distribution-table';
import RiskScale from '@/components/results/risk-scale';
import { hiddenAreasText } from '@/lib/results';
import type { AreaResults, RiskLevelInfo, RiskResults } from '@/types';

/** The results of the report (num. 7.7 e): the center, its categories, domains and areas, and what each level needs. */
export default function ReportResults({
    results,
    areas,
    levels,
}: {
    results: RiskResults;
    areas: AreaResults;
    levels: RiskLevelInfo[];
}) {
    if (!results.visible) {
        return (
            <p className="text-muted-foreground">
                Se recibieron {results.answered} cuestionarios: menos de 5, así
                que no se muestran resultados para no revelar respuestas.
            </p>
        );
    }

    const hidden = hiddenAreasText(areas.hidden);

    return (
        <div className="grid gap-6">
            <p>
                Cuántos de los {results.answered} cuestionarios quedan en cada
                nivel de riesgo. Cada cuestionario se calificó por sí solo; los
                resultados no se promedian.
            </p>
            <DistributionTable
                caption="Calificación final por nivel de riesgo"
                heading="Calificación final"
                rows={[
                    {
                        key: 'final',
                        label: 'Centro de trabajo',
                        levels: results.final,
                    },
                ]}
                levels={levels}
            />
            <DistributionTable
                caption="Categorías por nivel de riesgo"
                heading="Categoría"
                rows={results.categories}
                levels={levels}
            />
            <DistributionTable
                caption="Dominios por nivel de riesgo"
                heading="Dominio"
                rows={results.domains}
                levels={levels}
            />
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
            {hidden !== null && (
                <p className="text-sm text-muted-foreground">
                    {hidden}: tienen menos de 5 cuestionarios, o el resto se
                    podría deducir.
                </p>
            )}
            <h3 className="font-semibold">Necesidad de acción por nivel</h3>
            <RiskScale levels={levels} />
        </div>
    );
}
