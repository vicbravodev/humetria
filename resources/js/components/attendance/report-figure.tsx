/** One figure of the report (pantalla Eval-Cerrada: cards with a figure), with what it counts. */
export default function ReportFigure({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="flex flex-col gap-1 card px-5 py-[18px]">
            <span className="text-sm leading-5 text-muted-foreground">
                {label}
            </span>
            <span className="text-3xl leading-[38px] font-semibold tracking-[-0.02em] tabular-nums">
                {value}
            </span>
            <span className="text-caption text-pretty text-muted-foreground">
                {hint}
            </span>
        </div>
    );
}
