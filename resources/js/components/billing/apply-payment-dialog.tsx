import { useState } from 'react';
import AllocationFields from '@/components/billing/allocation-fields';
import FormDialog from '@/components/humetria/form-dialog';
import { Button } from '@/components/ui/button';
import { apply } from '@/routes/billing/payments';
import type { PaymentRow } from '@/types';

/** Applies what a payment left unapplied to statements with a balance: same limits as when it was recorded. */
export default function ApplyPaymentDialog({
    accountId,
    payment,
}: {
    accountId: string;
    payment: PaymentRow;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setOpen(true)}
                aria-label={`Aplicar saldo del pago ${payment.reference}`}
            >
                Aplicar saldo
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Aplicar saldo · ${payment.reference}`}
                description={`Quedan ${payment.unapplied} sin aplicar de este pago. Aplicarlos no cambia el acceso de ninguna empresa.`}
                form={apply.form(payment.id)}
                submit="Aplicar pago"
            >
                {(errors) => (
                    <AllocationFields
                        accountId={accountId}
                        error={errors['allocations'] ?? errors['payment']}
                    />
                )}
            </FormDialog>
        </>
    );
}
