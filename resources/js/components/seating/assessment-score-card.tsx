import ProgressBar from '@/components/humetria/progress-bar';
import { scoreSummary } from '@/lib/seating/assessment';

/**
 * «Puntaje» (board LS-Evaluacion): the points chosen so far of the seven conditions, out of 7 to 21. The level is not
 * computed here: the server computes it when the Comisión signs, with the DOF's tables.
 */
export default function AssessmentScoreCard({
    scores,
    keys,
}: {
    scores: Partial<Record<string, number>>;
    keys: string[];
}) {
    const { total, min, max } = scoreSummary(scores, keys);

    return (
        <section
            aria-labelledby="score"
            className="flex flex-col gap-3 card p-[22px]"
        >
            <h2 id="score" className="text-sm font-semibold">
                Puntaje
            </h2>
            <p className="flex items-baseline gap-2">
                <span className="text-5xl font-semibold tracking-[-0.02em] tabular-nums">
                    {total}
                </span>
                <span className="text-lg text-muted-foreground">de {max}</span>
            </p>
            <ProgressBar
                value={total}
                max={max}
                label={`${total} de ${max} puntos`}
            />
            <p className="flex justify-between text-sm text-muted-foreground">
                <span>{min} · mínimo</span>
                <span>{max} · máximo</span>
            </p>
            <p className="text-sm text-muted-foreground">
                El nivel de riesgo se calcula al firmar, con las tablas
                vigentes.
            </p>
        </section>
    );
}
