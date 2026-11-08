import { cn } from '@/lib/utils';

/** One figure of the report (pantalla Eval-Cerrada: cards with a figure), with what it counts. */
export default function ReportFigure({
    label,
    value,
    hint,
    alert = false,
}: {
    label: string;
    value: string;
    hint: string;
    /** A figure that asks for RH's attention when it is not zero. */
    alert?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1 card px-5 py-[18px]',
                alert && 'border-destructive',
            )}
        >
            <span
                className={cn(
                    'text-sm leading-5',
                    alert ? 'text-destructive' : 'text-muted-foreground',
                )}
            >
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
