import { cn } from '@/lib/utils';

/** One figure of an account: what it is, the number and, under it, a note when there is something to say. */
export default function AccountFigure({
    label,
    value,
    hint,
    className,
}: {
    label: string;
    value: string;
    hint?: string | undefined;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <dt className="text-caption font-medium tracking-[0.02em] text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
                {value}
            </dd>
            {hint !== undefined && (
                <dd className="text-sm text-muted-foreground">{hint}</dd>
            )}
        </div>
    );
}
