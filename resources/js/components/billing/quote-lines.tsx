import QuoteTotalRow from '@/components/billing/quote-total-row';
import type { Quote } from '@/types';

/** The lines of a quote, each with where its amount comes from, and the subtotal, the VAT and the total under them. */
export default function QuoteLines({ quote }: { quote: Quote }) {
    return (
        <>
            <ul className="grid gap-2.5">
                {quote.lines.map((line) => (
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
                <QuoteTotalRow label="Subtotal" amount={quote.subtotal} />
                <QuoteTotalRow label="IVA" amount={quote.vat} />
                <QuoteTotalRow label="Total" amount={quote.total} strong />
            </dl>
        </>
    );
}
