import CenterPicker from '@/components/disclosure/center-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { INCISOS } from '@/lib/disclosure';
import { publish } from '@/routes/tenant/disclosure/policy';

/**
 * Publishes the draft as the next version (criteria C-50 and C-51): the centers, the incisos of 5.1 it contemplates and the
 * review that every commitment is true in them. Responsibles and resources are asked of the draft: if one is missing the
 * server says which, here.
 */
export default function PublishDialog({
    open,
    onOpenChange,
    documentId,
    centersInUse,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentId: string;
    centersInUse: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Publicar la política"
            description="Se guarda como la siguiente versión y ya no se puede cambiar: es la evidencia de lo que el centro prometió. Las personas la recibirán por correo para confirmar que la leyeron."
            form={publish.form({ tenant: tenantSlug, document: documentId })}
            submit="Publicar"
        >
            {(errors) => (
                <>
                    <CenterPicker
                        inUse={centersInUse}
                        error={
                            errors['work_center_ids'] ??
                            errors['work_center_ids.0']
                        }
                    />
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            La política contempla (numeral 5.1)
                        </legend>
                        {INCISOS.map(({ value, label }) => (
                            <label
                                key={value}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-2.5 hover:bg-muted has-checked:border-primary has-checked:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring"
                            >
                                <input
                                    type="checkbox"
                                    name="covers[]"
                                    value={value}
                                    defaultChecked
                                    className="size-5 shrink-0 accent-primary"
                                />
                                <span>
                                    <span className="mr-1 font-medium">
                                        {value})
                                    </span>
                                    {label}
                                </span>
                            </label>
                        ))}
                        <InputError
                            message={
                                errors['covers'] ??
                                errors['covers.0'] ??
                                errors['covers.1'] ??
                                errors['covers.2']
                            }
                        />
                    </fieldset>
                    <div className="grid gap-2">
                        <label className="flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                name="reviewed"
                                value="1"
                                className="mt-0.5 size-5 shrink-0 accent-primary"
                                aria-describedby="publish-reviewed-error"
                            />
                            <span>
                                Revisé que cada compromiso y principio es cierto
                                en mis centros.
                            </span>
                        </label>
                        <InputError
                            id="publish-reviewed-error"
                            message={errors['reviewed']}
                        />
                    </div>
                    <InputError message={errors['responsibles']} />
                    <InputError message={errors['resources']} />
                </>
            )}
        </FormDialog>
    );
}
