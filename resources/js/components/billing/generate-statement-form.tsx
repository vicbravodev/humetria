import { Calculator } from 'lucide-react';
import StatementPreviewLines from '@/components/billing/statement-preview-lines';
import InputError from '@/components/humetria/input-error';
import MonthPicker from '@/components/humetria/month-picker';
import { Label } from '@/components/ui/label';
import { useLivePreview } from '@/hooks/use-live-preview';
import { parseStatementPreview } from '@/lib/billing-statements';
import { preview } from '@/routes/billing/statements';

/**
 * The month to bill and, under it, the lines that month would have, asked again when the month changes: the draft is made
 * only after seeing them.
 */
export default function GenerateStatementForm({
    companyId,
    defaultPeriod,
    error,
}: {
    companyId: string;
    defaultPeriod: string;
    error: string | undefined;
}) {
    const { anchorRef, result, errors, schedule } = useLivePreview(
        preview.url(companyId),
        parseStatementPreview,
    );
    const problem = error ?? errors['period'];

    return (
        <div ref={anchorRef} onChange={() => schedule()} className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="period">Mes</Label>
                <MonthPicker
                    id="period"
                    name="period"
                    defaultValue={defaultPeriod}
                    onChange={() => schedule()}
                    invalid={problem !== undefined}
                    describedBy="period-error"
                />
                <InputError id="period-error" message={problem} />
            </div>
            <aside
                aria-label="Lo que dirá el estado de cuenta"
                aria-live="polite"
                data-test="statement-preview"
                className="grid content-start gap-4 rounded-xl border bg-muted/40 p-5"
            >
                <h3 className="flex items-center gap-2 text-base font-semibold">
                    <Calculator aria-hidden className="size-4" />
                    Lo que dirá el estado de cuenta
                </h3>
                {result === null ? (
                    <p className="text-sm text-muted-foreground">
                        {problem ?? 'Calculando las líneas…'}
                    </p>
                ) : (
                    <StatementPreviewLines preview={result} />
                )}
            </aside>
        </div>
    );
}
