import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/vacations/holidays';

/** A day the company does not work besides those of the law: from then on it is not counted as a working day. */
export default function HolidayDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Nuevo día festivo"
            description="Las solicitudes que ya se hicieron no cambian: el festivo cuenta desde ahora."
            form={store.form(tenantSlug)}
            submit="Agregar festivo"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-date">Día</Label>
                        <DatePicker
                            id="holiday-date"
                            name="date"
                            invalid={errors['date'] !== undefined}
                        />
                        <InputError message={errors['date']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="holiday-name">Nombre</Label>
                        <Input
                            id="holiday-name"
                            name="name"
                            maxLength={80}
                            aria-invalid={
                                errors['name'] !== undefined || undefined
                            }
                        />
                        <InputError message={errors['name']} />
                    </div>
                </div>
            )}
        </FormDialog>
    );
}
