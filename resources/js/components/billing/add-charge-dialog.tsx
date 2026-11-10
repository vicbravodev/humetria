import { useState } from 'react';
import ChargeKindFields from '@/components/billing/charge-kind-fields';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { ChargeKind } from '@/lib/billing-form';
import { store } from '@/routes/billing/charges';
import type { AccountCompanyRow } from '@/types';

/** Adds an extra charge to a company with a plan: the accompaniment every period, or an implementation once. It is approved by who adds it. */
export default function AddChargeDialog({
    company,
}: {
    company: AccountCompanyRow;
}) {
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<ChargeKind>('one_time');

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setOpen(true)}
                aria-label={`Agregar cargo a ${company.trade_name}`}
            >
                Agregar cargo
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Agregar cargo · ${company.trade_name}`}
                description="Un cargo extra se suma al plan de la empresa cuando se hagan los estados de cuenta. Queda a tu nombre como quien lo aprobó."
                form={store.form(company.company_id)}
                submit="Guardar cargo"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <ChargeKindFields
                            kind={kind}
                            onChange={setKind}
                            error={errors['kind']}
                        />
                        <FormField
                            idPrefix="charge"
                            name="description"
                            label="Descripción"
                            maxLength={200}
                            autoComplete="off"
                            error={errors['description']}
                        />
                        <FormField
                            idPrefix="charge"
                            name="scope"
                            label="Alcance"
                            maxLength={200}
                            autoComplete="off"
                            error={errors['scope']}
                        />
                        <FormField
                            idPrefix="charge"
                            name="amount"
                            label="Importe sin IVA"
                            prefix="$"
                            inputMode="decimal"
                            autoComplete="off"
                            error={errors['amount']}
                        />
                        <div className="grid items-start gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="charge-charge_on">
                                    Se cobra desde
                                </Label>
                                <DatePicker
                                    id="charge-charge_on"
                                    name="charge_on"
                                    invalid={errors['charge_on'] !== undefined}
                                    describedBy="charge-charge_on-error"
                                />
                                <InputError
                                    id="charge-charge_on-error"
                                    message={errors['charge_on']}
                                />
                            </div>
                            {kind === 'recurring' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="charge-ends_on">
                                        Termina el (opcional)
                                    </Label>
                                    <DatePicker
                                        id="charge-ends_on"
                                        name="ends_on"
                                        invalid={
                                            errors['ends_on'] !== undefined
                                        }
                                        describedBy="charge-ends_on-error"
                                    />
                                    <InputError
                                        id="charge-ends_on-error"
                                        message={errors['ends_on']}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </FormDialog>
        </>
    );
}
