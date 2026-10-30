import ProgressBar from '@/components/humetria/progress-bar';
import { guideBreakdown } from '@/lib/campaigns';
import type { InstrumentProgress } from '@/types';

/** How many answered each guide, out of everyone invited to it, and where the rest stand. Never what they answered. */
export default function InstrumentsProgress({
    instruments,
}: {
    instruments: InstrumentProgress[];
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {instruments.map((instrument) => (
                <section
                    key={instrument.key}
                    className="flex flex-col gap-2 rounded-xl border bg-card p-6"
                >
                    <h3 className="text-sm text-muted-foreground">
                        {instrument.title}
                    </h3>
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {instrument.completed} de {instrument.invited}
                    </p>
                    <ProgressBar
                        value={instrument.completed}
                        max={instrument.invited}
                        label={`Respuestas de la ${instrument.title}`}
                    />
                    <p className="text-sm text-muted-foreground">
                        {guideBreakdown(instrument)}
                    </p>
                </section>
            ))}
        </div>
    );
}
