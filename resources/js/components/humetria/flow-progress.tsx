/** Where a question flow goes (board V2-Politica): «Pregunta 1 de 8», about how long is left and the bar. */
export default function FlowProgress({
    label,
    left,
    value,
    max,
}: {
    label: string;
    /** «Faltan unos 12 min», or nothing on the review. */
    left: string | null;
    value: number;
    max: number;
}) {
    const percent = max === 0 ? 0 : Math.min(100, (value / max) * 100);

    return (
        <div className="flex flex-col gap-2.5">
            <div className="flex justify-between gap-3 text-sm">
                <span className="font-medium">{label}</span>
                {left !== null && (
                    <span className="text-muted-foreground">{left}</span>
                )}
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={Math.min(value, max)}
                className="h-1.5 w-full overflow-hidden rounded-full bg-track"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
