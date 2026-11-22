import { useState } from 'react';
import FormSheet from '@/components/humetria/form-sheet';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { firstName } from '@/lib/attendance/correction-effects';
import { resolve } from '@/routes/tenant/attendance/weeks';
import type { OverviewDispute } from '@/types';

/**
 * RH answers a disputed week (criterio 5.2): the person's reason in their words and RH's note, which the person reads. The
 * week goes back to them to confirm or dispute again. Once.
 */
export default function ResolveDisputeSheet({
    name,
    dispute,
}: {
    name: string;
    dispute: OverviewDispute;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title={`Resolver la objeción de ${name}`}
            description={`La semana vuelve a ${firstName(name)} para que la confirme o la objete otra vez. Se resuelve una sola vez y queda en la bitácora.`}
            form={resolve.form({ tenant: tenantSlug, week: dispute.id })}
            submit="Resolver objeción"
            trigger={
                <SheetTrigger asChild>
                    <Button size="sm">Resolver</Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <>
                    <div className="flex flex-col gap-2 rounded-xl bg-muted/50 p-5">
                        <p className="text-lg text-pretty">
                            “{dispute.reason}”
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Lo dijo {name} al objetar su semana
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="dispute-note">
                            Nota para {firstName(name)}
                        </Label>
                        <Textarea
                            id="dispute-note"
                            name="note"
                            required
                            minLength={10}
                            maxLength={500}
                            rows={4}
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                            placeholder="Por ejemplo: Revisamos el lector y corregimos tu salida del martes. Confirma tu semana otra vez."
                        />
                        <InputError message={errors['note']} />
                    </div>
                </>
            )}
        </FormSheet>
    );
}
