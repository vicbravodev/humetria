import ArcMeter from '@/components/humetria/arc-meter';
import { percent } from '@/lib/evaluation';
import type { CampaignProgress } from '@/types';

/**
 * How the evaluation ended: how many answered the guide that measures it, against its sample or everyone (pantalla
 * Eval-Cerrada). The risk levels are the results' to tell.
 */
export default function ClosedSummary({
    progress,
    guide,
}: {
    progress: CampaignProgress;
    /** The guide that measures it: "Guía II". */
    guide: string;
}) {
    const share = percent(progress.completed, progress.target);
    const counted = `${progress.completed} de ${progress.target}`;

    return (
        <section
            aria-label="Resumen"
            className="grid gap-x-12 gap-y-7 rounded-xl border bg-card p-7 md:grid-cols-[minmax(0,1fr)_auto]"
        >
            <div className="flex flex-col justify-center gap-4">
                <span className="text-sm text-muted-foreground">
                    Participación final · {guide}
                </span>
                <p className="max-w-[520px] text-[22px] leading-8 font-medium tracking-[-0.01em] text-pretty">
                    <strong className="font-semibold">
                        {counted}{' '}
                        {progress.againstSample
                            ? 'cuestionarios de la muestra'
                            : 'personas'}
                    </strong>{' '}
                    contestaron la {guide}. Los niveles de riesgo, por área y
                    por dominio, están en los resultados.
                </p>
            </div>
            <div className="flex items-center justify-center md:border-l md:pl-7">
                <ArcMeter
                    value={progress.completed}
                    max={progress.target}
                    label={`${counted} contestaron`}
                    size="lg"
                >
                    <span className="text-[40px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {share} %
                    </span>
                    <span className="max-w-[100px] text-[13px] leading-4 text-muted-foreground">
                        {counted} contestaron
                    </span>
                </ArcMeter>
            </div>
        </section>
    );
}
