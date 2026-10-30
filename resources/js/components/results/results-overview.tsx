import ArcMeter from '@/components/humetria/arc-meter';
import RiskSummary from '@/components/results/risk-summary';
import { percentText } from '@/lib/results';
import type { Participation, RiskLevelInfo, RiskResults } from '@/types';

/**
 * The evaluation at a glance (pantallas: Eval-Cerrada): the final score of the Guía II or III in one sentence and one
 * bar, or why there is none to show yet, next to how many answered.
 */
export default function ResultsOverview({
    guide,
    participation,
    results,
    area,
    levels,
}: {
    /** The Guía II or III; null when the campaign applies only the Guía I, and then nothing shows. */
    guide: { title: string } | null;
    participation: Participation | null;
    /** Null while the evaluation runs. */
    results: RiskResults | null;
    /** The area the results are narrowed to; null for the whole center. */
    area: { label: string } | null;
    levels: RiskLevelInfo[];
}) {
    if (guide === null || participation === null) {
        return null;
    }

    const caption = `Calificación final · ${guide.title}${area === null ? '' : ` · ${area.label}`}`;
    const answered = `${participation.answered} de ${participation.invited} contestaron`;

    return (
        <section
            aria-label="Resumen"
            className="grid gap-x-12 gap-y-7 rounded-xl border bg-card p-5 sm:p-7 lg:grid-cols-2"
        >
            <div className="flex flex-col gap-4">
                <p className="text-sm text-muted-foreground">{caption}</p>
                {results?.visible === true ? (
                    <RiskSummary results={results} levels={levels} />
                ) : (
                    <p className="max-w-[520px] text-[17px] leading-[26px] text-muted-foreground">
                        {results === null
                            ? `Los resultados de la ${guide.title} se muestran al cerrar la evaluación: mientras sigue abierta, cada respuesta nueva diría qué contestó quien acaba de contestar.`
                            : `Sin resultados que mostrar: hay ${results.answered} cuestionarios y se necesitan al menos 5 para mostrarlos sin revelar respuestas.`}
                    </p>
                )}
            </div>
            <div className="flex items-center justify-center border-t pt-7 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-7">
                <ArcMeter
                    value={participation.answered}
                    max={participation.invited}
                    label={answered}
                    size="lg"
                >
                    <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {percentText(
                            participation.answered,
                            participation.invited,
                        )}
                    </span>
                    <span className="max-w-[100px] text-[13px] leading-4 text-muted-foreground">
                        {answered}
                    </span>
                </ArcMeter>
            </div>
        </section>
    );
}
