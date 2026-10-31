import ArcMeter from '@/components/humetria/arc-meter';
import { progressText } from '@/lib/telework/center';
import type { CenterProgress, ComplianceRow } from '@/types';

/** The arch of the center's dashboard (pantalla Eval-Activa): rows of table 10.3 done, with what is left beside it. */
export default function CenterProgressCard({
    progress,
    rows,
}: {
    progress: CenterProgress;
    rows: ComplianceRow[];
}) {
    const counts = [
        ['Cumplen', rows.filter((row) => row.state === 'done').length],
        ['Incompletos', rows.filter((row) => row.state === 'partial').length],
        ['Faltan', rows.filter((row) => row.state === 'missing').length],
        [
            'No aplican',
            rows.filter((row) => row.state === 'not_applicable').length,
        ],
    ] as const;

    return (
        <section
            aria-label="Avance de la tabla de inspección"
            className="flex flex-col items-center gap-6 rounded-xl border bg-card p-6 sm:flex-row"
        >
            <ArcMeter
                value={progress.done}
                max={progress.applicable}
                label={`${progressText(progress)} cumplidos`}
            >
                <span className="text-[44px] leading-[44px] font-semibold tabular-nums">
                    {progress.done}
                </span>
                <span className="text-[13px] leading-[18px] text-muted-foreground">
                    de {progress.applicable} renglones
                </span>
            </ArcMeter>
            <dl className="grid w-full grid-cols-2 gap-x-6 gap-y-3 sm:w-auto sm:min-w-[220px]">
                {counts.map(([term, value]) => (
                    <div key={term} className="flex flex-col gap-0.5">
                        <dt className="text-[13px] leading-[18px] text-muted-foreground">
                            {term}
                        </dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
