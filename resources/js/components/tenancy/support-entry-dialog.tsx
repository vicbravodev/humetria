import { router } from '@inertiajs/react';
import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { SUPPORT_MINUTES } from '@/lib/support-entry';
import { impersonate } from '@/routes/companies';

/**
 * Entering a company as support (tablero Flujos, «Entrar como soporte»): why, and for how long. The entry ends by itself when the
 * time is up; it never opens individual answers nor anyone's personal account.
 */
export default function SupportEntryDialog({
    tenantId,
    tradeName,
    open,
    onOpenChange,
}: {
    tenantId: string;
    tradeName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [minutes, setMinutes] = useState(30);

    return (
        <FormDialog
            open={open}
            onOpenChange={(isOpen) => {
                // Another person's session follows: nothing prefetched as the staff member carries over.
                if (isOpen) {
                    router.flushAll();
                }
                onOpenChange(isOpen);
            }}
            title={`Entrar a ${tradeName}`}
            description="Verás la empresa como la ve su dueño. No puedes ver respuestas individuales ni cambiar la cuenta personal de nadie."
            form={impersonate.form(tenantId)}
            submit="Entrar como soporte"
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="support-reason">Motivo</Label>
                        <Textarea
                            id="support-reason"
                            name="reason"
                            rows={2}
                            maxLength={500}
                            placeholder="Invitación rebotada, la dueña no recibe el correo"
                            aria-invalid={errors['reason'] !== undefined}
                            aria-describedby="support-reason-error"
                        />
                        <InputError
                            id="support-reason-error"
                            message={errors['reason']}
                        />
                    </div>
                    <ChoiceField
                        name="minutes"
                        legend="Duración"
                        options={SUPPORT_MINUTES}
                        value={minutes}
                        onChange={setMinutes}
                        columns={3}
                        error={errors['minutes']}
                    />
                </>
            )}
        </FormDialog>
    );
}
