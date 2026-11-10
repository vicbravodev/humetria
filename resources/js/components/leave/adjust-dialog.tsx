import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import PillChoice from '@/components/humetria/pill-choice';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { adjust } from '@/routes/tenant/vacations/employees';
import type { LeaveRecordPeriod } from '@/types';

type Tranche = 'statutory' | 'extra';

/** RH corrects what is left of a tranche of one period, in favor or against the person, and says why. */
export default function AdjustDialog({
    employeeId,
    period,
    onClose,
}: {
    employeeId: string;
    /** The period being adjusted; null while the dialog is closed. */
    period: LeaveRecordPeriod | null;
    onClose: () => void;
}) {
    const tenantSlug = useTenantSlug();
    const [tranche, setTranche] = useState<Tranche>('statutory');

    return (
        <FormDialog
            open={period !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Ajustar el periodo ${period?.year ?? ''}`}
            description="Queda en el libro de movimientos con su nota y en la bitácora. Nunca deja un tramo bajo cero."
            form={adjust.form({ tenant: tenantSlug, employee: employeeId })}
            submit="Registrar ajuste"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <input
                        type="hidden"
                        name="period"
                        value={period?.id ?? ''}
                    />
                    <PillChoice<Tranche>
                        name="tranche"
                        legend="Tramo"
                        options={[
                            { value: 'statutory', label: 'Legal' },
                            { value: 'extra', label: 'Adicional' },
                        ]}
                        value={tranche}
                        onChange={setTranche}
                    />
                    <InputError message={errors['tranche']} />
                    <div className="grid gap-2">
                        <Label htmlFor="adjust-days">Días</Label>
                        <Input
                            id="adjust-days"
                            name="days"
                            type="number"
                            step={1}
                            min={-365}
                            max={365}
                            inputMode="numeric"
                            aria-describedby="adjust-days-hint"
                            aria-invalid={
                                errors['days'] === undefined ? undefined : true
                            }
                        />
                        <p
                            id="adjust-days-hint"
                            className="text-meta text-muted-foreground"
                        >
                            Positivo a favor de la persona, negativo en su
                            contra.
                        </p>
                        <InputError message={errors['days']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="adjust-note">Motivo</Label>
                        <Textarea
                            id="adjust-note"
                            name="note"
                            rows={3}
                            maxLength={500}
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                        />
                        <InputError message={errors['note']} />
                    </div>
                </div>
            )}
        </FormDialog>
    );
}
