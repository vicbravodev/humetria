import ArcMeter from '@/components/humetria/arc-meter';
import ProgressBar from '@/components/humetria/progress-bar';
import { guideBreakdown } from '@/lib/campaigns';
import { arcCaption } from '@/lib/evaluation';
import type { CampaignProgress, InstrumentProgress } from '@/types';

/**
 * How far the evaluation has come: the guide that measures it on the arc, against its sample or everyone, and each
 * guide under it with where the rest stand. Never what anyone answered.
 */
export default function ProgressCard({
    progress,
    instruments,
}: {
    progress: CampaignProgress;
    instruments: InstrumentProgress[];
}) {
    const caption = arcCaption(progress);

    return (
        <article className="flex flex-col items-center gap-[18px] rounded-xl border bg-card p-6">
            <ArcMeter
                value={progress.completed}
                max={progress.target}
                label={`${progress.completed} ${caption}`}
                size="lg"
            >
                <span className="text-[46px] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                    {progress.completed}
                </span>
                <span className="max-w-[116px] text-[13px] leading-4 text-muted-foreground">
                    {caption}
                </span>
            </ArcMeter>
            <div className="grid w-full grid-cols-2 gap-3 border-t pt-4">
                {instruments.map((instrument) => (
                    <div
                        key={instrument.key}
                        className="flex min-w-0 flex-col gap-1.5"
                    >
                        <h3 className="text-xs text-muted-foreground">
                            {instrument.title}
                        </h3>
                        <p className="text-sm font-semibold tabular-nums">
                            {instrument.completed} de {instrument.invited}
                        </p>
                        <ProgressBar
                            value={instrument.completed}
                            max={instrument.invited}
                            label={`Respuestas de la ${instrument.title}`}
                        />
                        <p className="text-xs text-muted-foreground">
                            {guideBreakdown(instrument)}
                        </p>
                    </div>
                ))}
            </div>
        </article>
    );
}
