import QuoteTotalRow from '@/components/billing/quote-total-row';
import type { StatementPreview } from '@/types';

/** The lines of a month's statement, each with where its amount comes from, and the subtotal, the VAT and the total. */
export default function StatementPreviewLines({
    preview,
}: {
    preview: StatementPreview;
}) {
    const [vat] = preview.lines.slice(-1);
    const rest = preview.lines.slice(0, -1);

    return (
        <>
            <ul className="grid gap-2.5">
                {rest.map((line) => (
                    <li key={line.label} className="grid gap-0.5">
                        <div className="flex justify-between gap-4">
                            <span className="font-medium">{line.label}</span>
                            <span className="tabular-nums">{line.amount}</span>
                        </div>
                        <p className="text-caption text-muted-foreground">
                            {line.explanation}
                        </p>
                    </li>
                ))}
            </ul>
            <dl className="grid gap-1 border-t pt-3 text-sm">
                <QuoteTotalRow label="Subtotal" amount={preview.subtotal} />
                <QuoteTotalRow
                    label={vat?.label ?? 'IVA'}
                    amount={preview.vat}
                />
                <QuoteTotalRow label="Total" amount={preview.total} strong />
            </dl>
        </>
    );
}
