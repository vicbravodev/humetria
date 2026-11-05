import LevelBar from '@/components/humetria/level-bar';
import LevelLegend from '@/components/humetria/level-legend';
import PublicRiskGroupRow from '@/components/disclosure/public-risk-group';
import PublicBlock from '@/components/disclosure/public-block';
import type { PublicResults } from '@/types';

/**
 * The results the company published for a center (5.7 e and 7.8; criterio C-54): how many questionnaires fall in each risk level,
 * for the whole center. It says what the numbers count, because «5 en riesgo alto» is not a percentage nor an average.
 */
export default function PublicResultsBlock({
    results,
    bare = false,
}: {
    results: PublicResults;
    /** Inside the plain card of the page of the people: no card of its own. */
    bare?: boolean;
}) {
    const { published } = results;
    const levels =
        published?.state === 'available'
            ? published.levels.map((level) => ({ ...level, action: '' }))
            : [];

    return (
        <PublicBlock id="block-e" inciso="e" title={results.title} bare={bare}>
            {published === null && (
                <p className="text-muted-foreground">
                    Aún no hay resultados publicados.
                </p>
            )}
            {published?.state === 'below_minimum' && (
                <p className="text-muted-foreground">
                    Los resultados de este centro no se muestran porque
                    contestaron muy pocas personas: así se protege la
                    confidencialidad de quienes contestaron.
                </p>
            )}
            {published?.state === 'available' && (
                <>
                    <p className="text-sm text-muted-foreground">
                        Evaluación cerrada el {published.closedOn}. Los números
                        cuentan cuántos cuestionarios quedan en cada nivel de
                        riesgo; no son un promedio.
                    </p>
                    <div className="flex flex-col gap-2">
                        <span className="text-base font-medium">
                            Calificación final ({published.answered}{' '}
                            cuestionarios)
                        </span>
                        <LevelBar
                            counts={published.final}
                            levels={levels}
                            label="Calificación final"
                        />
                        <LevelLegend levels={levels} counts={published.final} />
                    </div>
                    <section
                        aria-label="Categorías"
                        className="flex flex-col gap-3"
                    >
                        <h3 className="text-base font-semibold">
                            Por categoría
                        </h3>
                        <ul className="flex flex-col gap-3">
                            {published.categories.map((group) => (
                                <PublicRiskGroupRow
                                    key={group.key}
                                    group={group}
                                    levels={published.levels}
                                />
                            ))}
                        </ul>
                    </section>
                    <section
                        aria-label="Dominios"
                        className="flex flex-col gap-3"
                    >
                        <h3 className="text-base font-semibold">Por dominio</h3>
                        <ul className="flex flex-col gap-3">
                            {published.domains.map((group) => (
                                <PublicRiskGroupRow
                                    key={group.key}
                                    group={group}
                                    levels={published.levels}
                                />
                            ))}
                        </ul>
                    </section>
                </>
            )}
        </PublicBlock>
    );
}
