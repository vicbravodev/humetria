import DiagramFrame from '@/components/blog/diagrams/diagram-frame';

/** Steps in order, each with its number: a list a person follows from the top, in two columns when there are many. */
export default function StepsDiagram({
    title,
    source,
    steps,
}: {
    title: string;
    source: string;
    steps: readonly string[];
}) {
    return (
        <DiagramFrame title={title} source={source}>
            <ol className="grid gap-3 sm:grid-cols-2">
                {steps.map((step, index) => (
                    <li
                        key={step}
                        className="flex items-center gap-3 rounded-xl bg-family-soft p-3"
                    >
                        <span
                            aria-hidden
                            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-family text-sm font-semibold text-background"
                        >
                            {index + 1}
                        </span>
                        <span className="text-meta font-medium">{step}</span>
                    </li>
                ))}
            </ol>
        </DiagramFrame>
    );
}
