import { useState } from 'react';
import FormField from '@/components/form-field';
import FormDialog from '@/components/humetria/form-dialog';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/billing/statements/adjustments';
import type { StatementDetail } from '@/types';

/** Corrects an issued statement without editing it: another statement that references it, for a charge or, with a minus sign, a credit. */
export default function AdjustStatementDialog({
    statement,
}: {
    statement: StatementDetail;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                Ajustar
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Ajustar el estado de cuenta de ${statement.period}`}
                description="El original no cambia: se emite otro que lo referencia, con su IVA. Un crédito va con signo menos."
                form={store.form(statement.id)}
                submit="Emitir ajuste"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <FormField
                            idPrefix="adjust"
                            name="description"
                            label="Concepto"
                            maxLength={200}
                            autoComplete="off"
                            error={errors['description']}
                        />
                        <FormField
                            idPrefix="adjust"
                            name="amount"
                            label="Importe sin IVA (negativo si es un crédito)"
                            prefix="$"
                            inputMode="decimal"
                            autoComplete="off"
                            error={errors['amount'] ?? errors['statement']}
                        />
                        <FormField
                            idPrefix="adjust"
                            name="reason"
                            label="Motivo"
                            maxLength={500}
                            autoComplete="off"
                            error={errors['reason']}
                        />
                    </div>
                )}
            </FormDialog>
        </>
    );
}
