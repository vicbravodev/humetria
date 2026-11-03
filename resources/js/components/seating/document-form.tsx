import { Form } from '@inertiajs/react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { CHOICE_CLASS } from '@/lib/seating/choice-class';
import { store } from '@/routes/tenant/seating/documents';
import type { SeatOption } from '@/types';

/** Uploads a PDF (pasos de Empleados-CSV): its kind, a title, its date, the incisos it covers and the file. */
export default function DocumentForm({
    workCenterId,
    kinds,
    incisos,
}: {
    workCenterId: string;
    kinds: SeatOption[];
    incisos: SeatOption[];
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
                        Guardar un documento
                    </h2>
                    <SeatChoiceField
                        name="kind"
                        legend="Tipo de documento"
                        options={kinds}
                        value={undefined}
                        error={errors['kind']}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="document-title">Título</Label>
                            <Input id="document-title" name="title" />
                            <InputError message={errors['title']} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="document-date">
                                Fecha del documento
                            </Label>
                            <DatePicker id="document-date" name="document_on" />
                            <InputError message={errors['document_on']} />
                        </div>
                    </div>
                    <fieldset className="grid gap-2 sm:grid-cols-2">
                        <legend className="mb-1 font-medium">
                            Incisos de la Quinta que cubre
                        </legend>
                        {incisos.map((inciso) => (
                            <label key={inciso.value} className={CHOICE_CLASS}>
                                <input
                                    type="checkbox"
                                    name="incisos[]"
                                    value={inciso.value}
                                    className="size-5 shrink-0 accent-primary"
                                />
                                {inciso.label}
                            </label>
                        ))}
                        <InputError message={errors['incisos.0']} />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="document-file">Archivo PDF</Label>
                        <Input
                            id="document-file"
                            name="file"
                            type="file"
                            accept="application/pdf"
                        />
                        <InputError message={errors['file']} />
                    </div>
                    <Button className="w-fit" disabled={processing}>
                        {processing && <Spinner />}
                        Guardar documento
                    </Button>
                </>
            )}
        </Form>
    );
}
