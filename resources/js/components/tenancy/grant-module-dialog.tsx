import { useState } from 'react';
import type { ReactNode } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import TenantReasonField from '@/components/tenancy/tenant-reason-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { returnTo } from '@/lib/company-360';
import { grant } from '@/routes/companies/modules';
import type { ModuleRow } from '@/types';

/** Gives the company a module it does not have, with a reason and, if it is a trial or a pilot, the last day. */
export default function GrantModuleDialog({
    companyId,
    module,
    trigger,
}: {
    companyId: string;
    module: ModuleRow;
    /** What opens it instead of the «Conceder» button (the switch of the summary). */
    trigger?: (open: () => void) => ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            {trigger === undefined ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setOpen(true)}
                    aria-label={`Conceder ${module.label}`}
                >
                    Conceder
                </Button>
            ) : (
                trigger(() => setOpen(true))
            )}
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Conceder ${module.label}`}
                description="Se habilita ya para esta empresa, sin cambiar su plan ni su precio. Queda en la bitácora."
                form={grant.form(companyId, returnTo(trigger))}
                submit="Conceder módulo"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <input type="hidden" name="module" value={module.key} />
                        <InputError
                            id="grant-module-error"
                            message={errors['module']}
                        />
                        <TenantReasonField error={errors['reason']} />
                        <div className="grid gap-2">
                            <Label htmlFor="grant-until">
                                Último día (opcional)
                            </Label>
                            <DatePicker
                                id="grant-until"
                                name="until"
                                invalid={errors['until'] !== undefined}
                                describedBy="grant-until-error"
                            />
                            <InputError
                                id="grant-until-error"
                                message={errors['until']}
                            />
                            <p className="text-sm text-muted-foreground">
                                Sin fecha, se queda hasta que alguien lo quite.
                            </p>
                        </div>
                    </div>
                )}
            </FormDialog>
        </>
    );
}
