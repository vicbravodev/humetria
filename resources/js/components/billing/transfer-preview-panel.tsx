import { ArrowRightLeft } from 'lucide-react';
import QuoteLines from '@/components/billing/quote-lines';
import { transferPriceChange } from '@/lib/billing-transfer';
import type { TransferPreview } from '@/types';

/**
 * What the move would do, before it is done: the price with the new payer, how the pricing changes, who loses or gains the
 * Integral discount at that cut, and the reminder that nobody gains or loses access.
 */
export default function TransferPreviewPanel({
    preview,
}: {
    preview: TransferPreview | null;
}) {
    const change = preview === null ? null : transferPriceChange(preview);

    return (
        <aside
            aria-label="Lo que pasaría"
            aria-live="polite"
            data-test="transfer-preview"
            className="grid content-start gap-4 rounded-xl border bg-muted/40 p-5"
        >
            <h3 className="flex items-center gap-2 text-base font-semibold">
                <ArrowRightLeft aria-hidden className="size-4" />
                Lo que pasaría
            </h3>
            {preview === null ? (
                <p className="text-sm text-muted-foreground">
                    Elige a dónde va y desde qué corte para ver el efecto.
                </p>
            ) : (
                <>
                    <p className="text-sm">
                        {preview.to === null
                            ? `${preview.from.name} deja de pagarla desde el ${preview.effective_on} y queda sin pagador.`
                            : `Pasa de ${preview.from.name} a ${preview.to.name} desde el ${preview.effective_on}.`}
                    </p>
                    {preview.profile !== null && (
                        <p className="text-sm">
                            Se cobra {preview.profile.after} en lugar de{' '}
                            {preview.profile.before}.
                        </p>
                    )}
                    {change !== null && (
                        <p className="rounded-lg bg-accent px-3 py-2 text-sm font-medium">
                            {change} al mes
                        </p>
                    )}
                    {preview.quote !== null &&
                        !preview.quote.requires_quotation && (
                            <QuoteLines quote={preview.quote} />
                        )}
                    {preview.effects.map((effect) => (
                        <p
                            key={effect.role}
                            className="text-sm text-muted-foreground"
                        >
                            {effect.text}
                        </p>
                    ))}
                    <p className="text-sm font-medium">{preview.access}</p>
                </>
            )}
        </aside>
    );
}
