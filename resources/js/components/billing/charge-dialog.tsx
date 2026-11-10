import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { chargeAction } from '@/lib/billing';
import { destroy, end } from '@/routes/billing/charges';
import type { AccountChargeRow } from '@/types';

/**
 * The superadmin ends a recurring charge (its last day) or removes a one-time charge nobody billed yet, always with the
 * reason, which stays in the bitácora. A billed one-time charge offers nothing: it is on a statement.
 */
export default function ChargeDialog({ charge }: { charge: AccountChargeRow }) {
    const [open, setOpen] = useState(false);
    const action = chargeAction(charge);

    if (action === null) {
        return null;
    }

    return (
        <>
            <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
                {action.button}
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={action.title}
                description={action.description}
                form={
                    action.kind === 'end'
                        ? end.form(charge.id)
                        : destroy.form(charge.id)
                }
                submit={action.submit}
                tone={action.kind === 'end' ? 'default' : 'destructive'}
            >
                {(errors) => (
                    <div className="grid gap-4">
                        {action.kind === 'end' && (
                            <div className="grid gap-2">
                                <Label htmlFor={`ends-on-${charge.id}`}>
                                    Último día
                                </Label>
                                <Input
                                    id={`ends-on-${charge.id}`}
                                    name="ends_on"
                                    type="date"
                                    required
                                    aria-invalid={
                                        errors['ends_on'] !== undefined
                                    }
                                />
                                <InputError message={errors['ends_on']} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor={`reason-${charge.id}`}>
                                Motivo
                            </Label>
                            <Input
                                id={`reason-${charge.id}`}
                                name="reason"
                                required
                                maxLength={500}
                                aria-invalid={errors['reason'] !== undefined}
                            />
                            <InputError message={errors['reason']} />
                            <InputError message={errors['charge']} />
                            <InputError message={errors['kind']} />
                        </div>
                    </div>
                )}
            </FormDialog>
        </>
    );
}
