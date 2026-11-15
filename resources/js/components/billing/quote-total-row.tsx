/** One row of the totals of a quote: the label at the left, the amount at the right; the last one is the strong one. */
export default function QuoteTotalRow({
    label,
    amount,
    strong = false,
}: {
    label: string;
    amount: string | null;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'flex justify-between gap-4 font-semibold'
                    : 'flex justify-between gap-4 text-muted-foreground'
            }
        >
            <dt>{label}</dt>
            <dd className="tabular-nums">{amount ?? '—'}</dd>
        </div>
    );
}
