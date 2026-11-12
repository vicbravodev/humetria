import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { grant } from '@/routes/billing/usage';

/** Adds tokens of Humi to this month's quota of the company, with the reason; the next month starts from the plan's again. */
export default function UsageGrantDialog({ companyId }: { companyId: string }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                Agregar tokens
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Agregar tokens de Humi"
                description="Se suman a la cuota de este mes y se acaban con él. Queda en la bitácora."
                form={grant.form(companyId)}
                submit="Agregar tokens"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="usage-quantity">
                                Cantidad de tokens
                            </Label>
                            <Input
                                id="usage-quantity"
                                name="quantity"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={100000000}
                                step={1}
                                required
                                aria-invalid={errors['quantity'] !== undefined}
                                aria-describedby="usage-quantity-error"
                            />
                            <InputError
                                id="usage-quantity-error"
                                message={errors['quantity']}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="usage-reason">Motivo</Label>
                            <Input
                                id="usage-reason"
                                name="reason"
                                required
                                maxLength={500}
                                aria-invalid={errors['reason'] !== undefined}
                                aria-describedby="usage-reason-error"
                            />
                            <InputError
                                id="usage-reason-error"
                                message={errors['reason']}
                            />
                        </div>
                    </div>
                )}
            </FormDialog>
        </>
    );
}
