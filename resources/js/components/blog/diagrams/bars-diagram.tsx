import DiagramFrame from '@/components/blog/diagrams/diagram-frame';

export type Bar = { label: string; value: number; note?: string };

/** One horizontal bar per row, all on the same scale (`max`), with the number written at its end: it reads as a table and as a shape. The bar is an SVG whose width is an attribute, not a style, so the server can render it. */
export default function BarsDiagram({
    title,
    source,
    rows,
    max,
    unit,
}: {
    title: string;
    source: string;
    rows: readonly Bar[];
    max: number;
    unit: string;
}) {
    return (
        <DiagramFrame title={title} source={source}>
            <ul className="grid gap-2.5">
                {rows.map((row) => (
                    <li
                        key={row.label}
                        className="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[7rem_minmax(0,1fr)]"
                    >
                        <span className="text-meta text-muted-foreground">
                            {row.label}
                        </span>
                        <span className="flex items-center gap-3">
                            {/* SVG attributes, not a style: the server renders this and the CSP blocks a style attribute. */}
                            <svg
                                role="img"
                                aria-label={`${row.label}: ${row.value} ${unit}`}
                                className="h-3 w-full flex-1"
                            >
                                <rect
                                    width="100%"
                                    height="100%"
                                    rx="6"
                                    className="fill-family-soft"
                                />
                                <rect
                                    width={`${(row.value / max) * 100}%`}
                                    height="100%"
                                    rx="6"
                                    className="fill-family"
                                />
                            </svg>
                            <span className="w-24 shrink-0 text-meta font-semibold tabular-nums">
                                {row.value} {unit}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </DiagramFrame>
    );
}
