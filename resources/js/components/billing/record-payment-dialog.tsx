import { Landmark } from 'lucide-react';
import { useState } from 'react';
import AllocationFields from '@/components/billing/allocation-fields';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/billing/payments';

/** Records a transfer (SPEI) of the account and applies it to the statements it settles. Paying does not change any company's access. */
export default function RecordPaymentDialog({
    accountId,
    today,
}: {
    accountId: string;
    today: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Landmark aria-hidden />
                Registrar pago
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Registrar pago"
                description="Una transferencia que la cuenta ya hizo. Registrarla no cambia el acceso de ninguna empresa: eso se decide aparte."
                form={store.form(accountId)}
                submit="Guardar pago"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <FormField
                            idPrefix="payment"
                            name="amount"
                            label="Importe recibido"
                            prefix="$"
                            inputMode="decimal"
                            autoComplete="off"
                            error={errors['amount']}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="payment-paid_on">
                                Fecha del pago
                            </Label>
                            <DatePicker
                                id="payment-paid_on"
                                name="paid_on"
                                defaultValue={today}
                                invalid={errors['paid_on'] !== undefined}
                                describedBy="payment-paid_on-error"
                            />
                            <InputError
                                id="payment-paid_on-error"
                                message={errors['paid_on']}
                            />
                        </div>
                        <FormField
                            idPrefix="payment"
                            name="reference"
                            label="Referencia o clave de rastreo"
                            maxLength={120}
                            autoComplete="off"
                            error={errors['reference']}
                        />
                        <FormField
                            idPrefix="payment"
                            name="note"
                            label="Nota (opcional)"
                            maxLength={1000}
                            autoComplete="off"
                            error={errors['note']}
                        />
                        <AllocationFields
                            accountId={accountId}
                            error={errors['allocations']}
                        />
                    </div>
                )}
            </FormDialog>
        </>
    );
}
