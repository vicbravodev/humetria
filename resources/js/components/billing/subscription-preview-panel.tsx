import { Calculator } from 'lucide-react';
import SubscriptionPreviewAnswer from '@/components/billing/subscription-preview-answer';
import type { SubscriptionPreview } from '@/types';

/**
 * The price of the plan being typed, before it is saved: what each line comes from, the VAT, the total, and what changes
 * against what the company pays now. A size the table does not reach says it needs a quotation instead of an amount.
 */
export default function SubscriptionPreviewPanel({
    preview,
}: {
    preview: SubscriptionPreview | null;
}) {
    return (
        <aside
            aria-label="Lo que costaría"
            aria-live="polite"
            data-test="plan-preview"
            className="grid content-start gap-4 rounded-xl border bg-muted/40 p-5"
        >
            <h3 className="flex items-center gap-2 text-base font-semibold">
                <Calculator aria-hidden className="size-4" />
                Lo que costaría al mes
            </h3>
            {preview === null ? (
                <p className="text-sm text-muted-foreground">
                    Calculando el importe…
                </p>
            ) : (
                <SubscriptionPreviewAnswer preview={preview} />
            )}
        </aside>
    );
}
