import QuoteLines from '@/components/billing/quote-lines';
import { priceChange } from '@/lib/billing-preview';
import type { SubscriptionPreview } from '@/types';

/** What the server answered for the plan being typed: the change in price, the lines, the first partial month and the discount note. */
export default function SubscriptionPreviewAnswer({
    preview,
}: {
    preview: SubscriptionPreview;
}) {
    const change = priceChange(preview);
    const first = preview.first_period;

    if (preview.quote.requires_quotation) {
        return (
            <p className="text-sm">
                {preview.quote.explanation}. Pon un precio negociado para fijar
                el importe.
            </p>
        );
    }

    return (
        <>
            {change !== null && (
                <p
                    data-test="plan-price-change"
                    className="rounded-lg bg-accent px-3 py-2 text-sm font-medium"
                >
                    {change} al mes
                </p>
            )}
            {preview.scheduled !== null && (
                <p
                    data-test="plan-scheduled"
                    className="text-sm text-muted-foreground"
                >
                    Lo que baja entra en vigor el{' '}
                    {preview.scheduled.effective_on}: hasta entonces la empresa
                    conserva lo que tiene, y no hay crédito por los días
                    anteriores. Lo que sube aplica hoy.
                </p>
            )}
            <QuoteLines quote={preview.quote} />
            {first !== null && first.subtotal !== null && (
                <p className="text-sm text-muted-foreground">
                    Primer mes, en parte: {first.subtotal} sin IVA.
                </p>
            )}
            {preview.eligibility !== null && (
                <p className="text-sm text-muted-foreground">
                    {preview.eligibility.reason}
                </p>
            )}
        </>
    );
}
