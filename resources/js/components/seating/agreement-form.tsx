import { Form } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import AgreementNumbersFields from '@/components/seating/agreement-numbers-fields';
import DatePicker from '@/components/humetria/date-picker';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { positions } from '@/routes/tenant/seating/seats';
import { store } from '@/routes/tenant/seating/pause-agreements';

/** Records an agreement on pauses for a position of the center, with how it was reached and its evidence. */
export default function AgreementForm({
    workCenterId,
}: {
    workCenterId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...store.form([tenantSlug, workCenterId])}
            options={{ preserveScroll: true }}
            resetOnSuccess
            noValidate
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                        Registrar un acuerdo de pausas
                    </h2>
                    <div className="grid gap-2">
                        <Label htmlFor="agreement-position">Puesto</Label>
                        <Combobox
                            id="agreement-position"
                            name="position_id"
                            source={serverSource(
                                positions.url({
                                    tenant: tenantSlug,
                                    workCenter: workCenterId,
                                }),
                            )}
                            placeholder="Busca por nombre"
                        />
                        <InputError message={errors['position_id']} />
                    </div>
                    <AgreementNumbersFields errors={errors} />
                    <div className="grid gap-2">
                        <Label htmlFor="agreement-date">
                            Fecha del acuerdo
                        </Label>
                        <DatePicker id="agreement-date" name="agreed_on" />
                        <InputError message={errors['agreed_on']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="agreement-how">Cómo se acordó</Label>
                        <Textarea
                            id="agreement-how"
                            name="how_agreed"
                            rows={2}
                        />
                        <InputError message={errors['how_agreed']} />
                    </div>
                    <div className="grid gap-2">
                        <p className="font-medium">Evidencia</p>
                        <FileDropZone
                            id="agreement-evidence"
                            name="evidence"
                            accept="application/pdf,image/jpeg,image/png"
                            title="Arrastra la evidencia aquí o elígela"
                            hint="PDF, JPG o PNG"
                            invalid={errors['evidence'] !== undefined}
                            describedBy="agreement-evidence-error"
                        />
                        <InputError
                            id="agreement-evidence-error"
                            message={errors['evidence']}
                        />
                    </div>
                    <Button className="w-fit" disabled={processing}>
                        {processing && <Spinner />}
                        Registrar acuerdo
                    </Button>
                </>
            )}
        </Form>
    );
}
