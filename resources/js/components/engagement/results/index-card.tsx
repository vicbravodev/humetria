import TrendBadge from '@/components/engagement/results/trend-badge';
import ArcMeter from '@/components/humetria/arc-meter';
import ProtectedCell from '@/components/humetria/protected-cell';
import type { Delta } from '@/lib/engagement/format';

/**
 * The engagement index as the one protagonist figure (pantalla Clima · Resultados): its favorable share inside the arch
 * of the logo, how it moved against the previous survey and whether people want to stay one more year. Participation
 * lives in the fact sheet, so there is one percentage here, not two.
 */
export default function IndexCard({
    index,
    delta,
    stay,
    previousName,
}: {
    /** Null when one of the three questions behind it is hidden. */
    index: number | null;
    delta: Delta | null;
    stay: number | null;
    previousName: string | null;
}) {
    return (
        <section
            aria-labelledby="engagement-index"
            className="flex min-w-0 flex-col gap-5 card p-5 sm:p-6"
        >
            <h2 id="engagement-index" className="text-lg font-semibold">
                Índice de compromiso
            </h2>
            {index === null ? (
                <div className="flex flex-col gap-2">
                    <ProtectedCell />
                    <p className="text-sm text-muted-foreground">
                        Menos de 5 respuestas en alguna de sus tres preguntas:
                        no se muestra.
                    </p>
                </div>
            ) : (
                <div className="flex flex-col items-center gap-3">
                    <ArcMeter
                        value={index}
                        max={100}
                        label={`${index} % de las respuestas al compromiso son favorables`}
                        showPercent
                    />
                    <p className="-mt-1 text-sm text-muted-foreground">
                        respuestas favorables
                    </p>
                    {delta !== null && previousName !== null && (
                        <p className="flex flex-wrap justify-center gap-x-2 text-sm">
                            <TrendBadge delta={delta} />
                            <span className="text-muted-foreground">
                                frente a {previousName}
                            </span>
                        </p>
                    )}
                </div>
            )}
            <p className="flex flex-wrap items-center justify-center gap-x-2 border-t pt-4 text-sm">
                Quieren seguir un año más:
                {stay === null ? (
                    <ProtectedCell />
                ) : (
                    <strong className="font-semibold tabular-nums">
                        {stay} %
                    </strong>
                )}
            </p>
        </section>
    );
}
