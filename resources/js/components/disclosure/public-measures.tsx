import type { PublicMeasure } from '@/types';

/** The measures of the Programa the company chose to show: what, where it stands and by when. Nothing else of the Programa. */
export default function PublicMeasures({
    measures,
}: {
    measures: PublicMeasure[];
}) {
    return (
        <section
            aria-label="Medidas del Programa de intervención"
            className="flex flex-col gap-2"
        >
            <h3 className="text-base font-semibold">
                Del Programa de intervención
            </h3>
            <ul className="flex flex-col divide-y rounded-lg border">
                {measures.map((measure) => (
                    <li
                        key={measure.title}
                        className="flex flex-col gap-0.5 px-4 py-3"
                    >
                        <span className="font-medium text-pretty">
                            {measure.title}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {measure.statusLabel} · para el {measure.dueOn}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
