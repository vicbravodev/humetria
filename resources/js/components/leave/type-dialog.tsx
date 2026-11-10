import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store, update } from '@/routes/tenant/vacations/types';
import type { LeaveTypeRow } from '@/types';

/** A leave type of the company: a new one or the one being changed. Only its name and whether it is paid. */
export default function TypeDialog({
    open,
    onOpenChange,
    type,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The type being changed; null for a new one. */
    type: LeaveTypeRow | null;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                type === null ? 'Nuevo tipo de permiso' : 'Cambiar el permiso'
            }
            description="No usa días de vacaciones. Solo RH y quien aprueba ven qué permiso es."
            form={
                type === null
                    ? store.form(tenantSlug)
                    : update.form({ tenant: tenantSlug, type: type.id })
            }
            submit="Guardar"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="type-name">Nombre</Label>
                        <Input
                            id="type-name"
                            name="name"
                            maxLength={80}
                            defaultValue={type?.name}
                            aria-invalid={
                                errors['name'] !== undefined || undefined
                            }
                        />
                        <InputError message={errors['name']} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="type-paid"
                            name="paid"
                            value="1"
                            defaultChecked={type?.paid ?? true}
                        />
                        <Label htmlFor="type-paid">Con goce de sueldo</Label>
                    </div>
                </div>
            )}
        </FormDialog>
    );
}
