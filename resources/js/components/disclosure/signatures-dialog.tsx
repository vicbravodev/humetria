import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import EvidenceField from '@/components/disclosure/evidence-field';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { store } from '@/routes/tenant/disclosure/dissemination/records';
import { options } from '@/routes/tenant/work-centers';

/**
 * «Registrar firmas en papel» (board V4-Difusion-Registro, decisión A11): for who has no email, the printed list they signed —
 * where, on which day, how many signed and the scanned sheet. It counts as the policy put in front of the people of that center.
 */
export default function SignaturesDialog({
    open,
    onOpenChange,
    today,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    today: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar firmas en papel"
            description="Registra cuántas personas firmaron la lista y guarda la hoja escaneada como evidencia. Una vez registrada no se puede cambiar."
            form={store.form(tenantSlug)}
            submit="Registrar"
        >
            {(errors) => (
                <>
                    <input type="hidden" name="medium" value="signed_list" />
                    <input type="hidden" name="covered_items[]" value="a" />
                    <div className="grid gap-2">
                        <Label htmlFor="signatures-center">
                            Centro de trabajo
                        </Label>
                        <Combobox
                            id="signatures-center"
                            name="work_center_id"
                            source={serverSource(options.url(tenantSlug))}
                            placeholder="Busca un centro"
                            invalid={errors['work_center_id'] !== undefined}
                        />
                        <InputError message={errors['work_center_id']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="signatures-count">
                            Cuántas personas firmaron
                        </Label>
                        <Input
                            id="signatures-count"
                            name="signatures"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            className="max-w-[160px]"
                            aria-invalid={
                                errors['signatures'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError message={errors['signatures']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="signatures-day">
                            Día en que firmaron
                        </Label>
                        <DatePicker
                            id="signatures-day"
                            name="done_on"
                            defaultValue={today}
                            max={today}
                            invalid={errors['done_on'] !== undefined}
                        />
                        <InputError message={errors['done_on']} />
                    </div>
                    <EvidenceField
                        id="signatures-sheet"
                        label="La hoja firmada"
                        title="Arrastra la hoja escaneada aquí o elígela"
                        hint="PDF, JPG o PNG. Lleva nombres: solo la ve quien administra la difusión."
                        error={errors['evidence']}
                    />
                </>
            )}
        </FormDialog>
    );
}
