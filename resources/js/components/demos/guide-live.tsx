import ProgressBar from '@/components/humetria/progress-bar';
import { liveCount } from '@/lib/demos/guide';

/** «Qué está pasando»: the live sentence, over a bar when it counts out of a total («Llegaron 3 de 5 respuestas»). */
export default function GuideLive({ live }: { live: string }) {
    const count = liveCount(live);

    return (
        <div className="flex flex-col gap-1.5">
            {count !== null && count.total !== null && (
                <ProgressBar
                    value={count.count}
                    max={count.total}
                    label={live}
                />
            )}
            <p className="tabular-nums">{live}</p>
        </div>
    );
}
