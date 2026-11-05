import type { ChannelSummary } from '@/types';

/**
 * «Lo que ha llegado» (board V4-Quejas-Canal, decisión C5): how many cases by state as a list, so «menos de 5» never breaks in
 * a narrow card. Under the minimum the exact number is not shown, and never by center.
 */
export default function ChannelArrivals({
    summary,
}: {
    summary: ChannelSummary;
}) {
    return (
        <section
            aria-labelledby="arrivals"
            className="flex flex-col gap-4 card p-6"
        >
            <h2 id="arrivals" className="text-lg font-semibold">
                Lo que ha llegado
            </h2>
            <dl className="flex flex-col divide-y rounded-lg border">
                {summary.byStatus.map((cell) => (
                    <div
                        key={cell.value}
                        className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 px-4 py-2.5"
                    >
                        <dt className="text-muted-foreground">{cell.label}</dt>
                        <dd className="font-semibold tabular-nums">
                            {cell.count === null
                                ? `menos de ${summary.minimum}`
                                : cell.count}
                        </dd>
                    </div>
                ))}
            </dl>
            <p className="text-caption text-muted-foreground">
                Con menos de {summary.minimum} no mostramos la cifra exacta,
                para que nadie pueda deducir quién escribió. Tampoco se muestran
                por centro.
            </p>
        </section>
    );
}
