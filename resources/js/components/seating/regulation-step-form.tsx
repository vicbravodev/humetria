import { Form } from '@inertiajs/react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { advance } from '@/routes/tenant/seating/regulation';
import type { RegulationStep } from '@/types';

const TITLES: Record<RegulationStep, string> = {
    signed: 'Registrar la firma de la comisión mixta',
    deposited: 'Registrar el depósito',
    distributed: 'Registrar el reparto y la publicación',
};

/** The next step, with only what it needs. Humetría records it; the deposit itself is made by the company before the authority. */
export default function RegulationStepForm({
    workCenterId,
    step,
}: {
    workCenterId: string;
    step: RegulationStep;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...advance.form([tenantSlug, workCenterId])}
            options={{ preserveScroll: true }}
            noValidate
            className="grid gap-4 print:hidden"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                        {TITLES[step]}
                    </h2>
                    <input type="hidden" name="to" value={step} />
                    {step === 'signed' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="signed-on">
                                    Fecha de la firma
                                </Label>
                                <DatePicker id="signed-on" name="signed_on" />
                                <InputError message={errors['signed_on']} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="signed-file">
                                    Reglamento firmado (PDF)
                                </Label>
                                <Input
                                    id="signed-file"
                                    name="file"
                                    type="file"
                                    accept="application/pdf"
                                />
                                <InputError message={errors['file']} />
                            </div>
                        </>
                    )}
                    {step === 'deposited' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="deposited-on">
                                    Fecha del depósito
                                </Label>
                                <DatePicker
                                    id="deposited-on"
                                    name="deposited_on"
                                />
                                <InputError message={errors['deposited_on']} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="deposit-folio">
                                    Folio del depósito
                                </Label>
                                <Input
                                    id="deposit-folio"
                                    name="deposit_folio"
                                />
                                <InputError message={errors['deposit_folio']} />
                            </div>
                        </>
                    )}
                    {step === 'distributed' && (
                        <div className="grid gap-2">
                            <Label htmlFor="distributed-on">
                                Fecha del reparto
                            </Label>
                            <DatePicker
                                id="distributed-on"
                                name="distributed_on"
                            />
                            <InputError message={errors['distributed_on']} />
                        </div>
                    )}
                    <InputError message={errors['to'] ?? errors['parts']} />
                    <Button className="w-fit" disabled={processing}>
                        {processing && <Spinner />}
                        Registrar
                    </Button>
                </>
            )}
        </Form>
    );
}
