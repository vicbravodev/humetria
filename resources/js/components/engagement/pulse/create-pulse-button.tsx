import { useState } from 'react';
import ModeOptions from '@/components/engagement/pulse/mode-options';
import FormDialog from '@/components/humetria/form-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/pulse';
import type { PulseModeOption } from '@/types';

/**
 * «Crear un pulso ahora» besides the schedule: choosing what to look at is a step in a dialog, not the screen. Only the
 * draft is made; nobody receives anything until it is launched.
 */
export default function CreatePulseButton({
    modes,
}: {
    modes: PulseModeOption[];
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                Crear un pulso ahora
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Crear un pulso"
                description="Se crea el borrador: nadie recibe nada hasta que lo lances."
                form={store.form(tenantSlug)}
                submit="Crear borrador"
            >
                {(errors) => (
                    <ModeOptions modes={modes} error={errors['mode']} />
                )}
            </FormDialog>
        </>
    );
}
