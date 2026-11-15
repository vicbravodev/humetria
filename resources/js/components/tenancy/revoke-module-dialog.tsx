import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import TenantReasonField from '@/components/tenancy/tenant-reason-field';
import { Button } from '@/components/ui/button';
import { revoke } from '@/routes/companies/modules';
import type { ModuleRow } from '@/types';

/** Takes a module away from the company by hand, with a reason: its pages and jobs stop and its data stays. */
export default function RevokeModuleDialog({
    companyId,
    module,
}: {
    companyId: string;
    module: ModuleRow;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setOpen(true)}
                aria-label={`Quitar ${module.label}`}
            >
                Quitar
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Quitar ${module.label}`}
                description="Se esconde del menú y sus pantallas, envíos y portales dejan de funcionar. Sus datos se conservan y vuelven si lo concedes otra vez."
                form={revoke.form(companyId)}
                submit="Quitar módulo"
                tone="destructive"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <input type="hidden" name="module" value={module.key} />
                        <InputError
                            id="revoke-module-error"
                            message={errors['module']}
                        />
                        <TenantReasonField error={errors['reason']} />
                    </div>
                )}
            </FormDialog>
        </>
    );
}
