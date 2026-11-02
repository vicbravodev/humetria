import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/seating/regulation';
import type { RegulationRecord } from '@/types';

const PARTS = [
    { name: 'risk_factors', label: 'Factores de riesgo' },
    { name: 'pauses', label: 'Pausas' },
    {
        name: 'seat_location',
        label: 'Ubicación y disponibilidad de los asientos',
    },
    { name: 'usage_rule', label: 'Norma de uso del asiento' },
] as const;

/** The four parts of the draft, editable until it is signed. */
export default function RegulationPartsForm({
    workCenterId,
    parts,
}: {
    workCenterId: string;
    parts: RegulationRecord['parts'];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...update.form([tenantSlug, workCenterId])}
            options={{ preserveScroll: true }}
            noValidate
            className="grid gap-5 print:hidden"
        >
            {({ processing, errors }) => (
                <>
                    {PARTS.map((part) => (
                        <div key={part.name} className="grid gap-2">
                            <Label
                                htmlFor={`regulation-${part.name}`}
                                className="text-base font-semibold"
                            >
                                {part.label}
                            </Label>
                            <Textarea
                                id={`regulation-${part.name}`}
                                name={part.name}
                                rows={6}
                                defaultValue={parts[part.name]}
                            />
                            <InputError message={errors[part.name]} />
                        </div>
                    ))}
                    <InputError message={errors['status']} />
                    <Button
                        variant="outline"
                        className="w-fit"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Guardar borrador
                    </Button>
                </>
            )}
        </Form>
    );
}
