import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { reverse } from '@/routes/billing/payments';
import type { PaymentRow } from '@/types';

/** Reverses a payment recorded by mistake, with the reason: it stays on record and what it paid is owed again. */
export default function ReversePaymentDialog({
    payment,
}: {
    payment: PaymentRow;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setOpen(true)}
                aria-label={`Revertir el pago ${payment.reference}`}
            >
                Revertir
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Revertir pago · ${payment.reference}`}
                description="El pago no se borra: queda en la lista como revertido, con tu motivo, y lo que había pagado vuelve a deberse."
                form={reverse.form(payment.id)}
                submit="Revertir pago"
                tone="destructive"
            >
                {(errors) => (
                    <div className="grid gap-2">
                        <Label htmlFor={`reverse-${payment.id}-reason`}>
                            Motivo
                        </Label>
                        <Textarea
                            id={`reverse-${payment.id}-reason`}
                            name="reason"
                            maxLength={500}
                            rows={3}
                            aria-invalid={errors['reason'] !== undefined}
                        />
                        <InputError
                            id={`reverse-${payment.id}-reason-error`}
                            message={errors['reason'] ?? errors['payment']}
                        />
                    </div>
                )}
            </FormDialog>
        </>
    );
}
