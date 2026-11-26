import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { start } from '@/routes/announcements/maintenance';

const WORD = 'MANTENIMIENTO';

/**
 * Puts the whole platform in maintenance: the reason, and the word typed before the button wakes up, so nobody stops every
 * company by a slip of the hand. The server checks the word too.
 */
export default function MaintenanceDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [typed, setTyped] = useState('');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Activar mantenimiento"
            description="Todas las empresas pasan a solo lectura hasta que lo termines: ven su información pero no guardan cambios. Las encuestas, el canal de denuncias y el portal de cada persona siguen funcionando."
            form={start.form()}
            submit="Activar mantenimiento"
            tone="destructive"
            blocked={typed.trim() !== WORD}
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="maintenance-reason">Motivo</Label>
                        <Textarea
                            id="maintenance-reason"
                            name="reason"
                            maxLength={500}
                            rows={2}
                            aria-invalid={errors['reason'] !== undefined}
                        />
                        <InputError message={errors['reason']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="maintenance-confirmation">
                            Escribe «{WORD}» para confirmar
                        </Label>
                        <Input
                            id="maintenance-confirmation"
                            name="confirmation"
                            autoComplete="off"
                            value={typed}
                            onChange={(event) => setTyped(event.target.value)}
                            aria-invalid={errors['confirmation'] !== undefined}
                        />
                        <InputError message={errors['confirmation']} />
                    </div>
                </>
            )}
        </FormDialog>
    );
}
