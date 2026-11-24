import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import TimePicker from '@/components/humetria/time-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { firstName } from '@/lib/attendance/correction-effects';
import { correctedAt } from '@/lib/attendance/offline-marks';
import { reject } from '@/routes/tenant/attendance/offline';
import type { OfflineMarkDetail } from '@/types';

/**
 * «Es incorrecta»: the note the person reads (obligatory) and the time that is right, proposed with the one the person
 * declared when it differs from the one counted. Without a time (the mark voided) the mark leaves the record. The original
 * stays, as with any correction, and the answered week reopens.
 */
export default function OfflineIncorrectDialog({
    detail,
    open,
    onOpenChange,
}: {
    detail: OfflineMarkDetail;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    const [time, setTime] = useState(detail.proposedAt.split(' ')[1] ?? '');
    const [voided, setVoided] = useState(false);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Esta marca es incorrecta"
            description={`La marca original se conserva y queda corregida a tu nombre. ${firstName(detail.person.name)} verá tu nota en «Mi semana».`}
            form={reject.form({ tenant: tenantSlug, mark: detail.id })}
            submit="Corregir marca"
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="offline-correct-time">
                            Hora correcta
                        </Label>
                        <TimePicker
                            id="offline-correct-time"
                            value={time}
                            onChange={setTime}
                            className="w-36"
                        />
                        <input
                            type="hidden"
                            name="corrected_at"
                            value={correctedAt(detail.proposedAt, time, voided)}
                        />
                        <InputError message={errors['corrected_at']} />
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Checkbox
                            id="offline-void"
                            checked={voided}
                            onCheckedChange={(checked) =>
                                setVoided(checked === true)
                            }
                        />
                        <Label htmlFor="offline-void">
                            Anular la marca (no debió existir)
                        </Label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offline-note">
                            Nota para {firstName(detail.person.name)}
                        </Label>
                        <Textarea
                            id="offline-note"
                            name="note"
                            required
                            minLength={5}
                            maxLength={500}
                            rows={3}
                            aria-invalid={
                                errors['note'] === undefined ? undefined : true
                            }
                            placeholder="Por ejemplo: Entraste a las 9:20; así lo muestra la cámara de la puerta."
                        />
                        <InputError message={errors['note']} />
                    </div>
                </>
            )}
        </FormDialog>
    );
}
