import ProgressBar from '@/components/humetria/progress-bar';
import type { DemoGuideStep } from '@/types/demo-guide';

/** «Qué está pasando»: the live sentence, over a bar when it counts out of a total («Llegaron 3 de 5 respuestas»). */
export default function GuideLive({
    live,
    count,
    of,
}: Pick<DemoGuideStep, 'count' | 'of'> & { live: string }) {
    return (
        <div className="flex flex-col gap-1.5">
            {count !== null && of !== null && (
                <ProgressBar value={count} max={of} label={live} />
            )}
            <p className="tabular-nums">{live}</p>
        </div>
    );
}
