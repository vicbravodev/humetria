import { Form, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import AspirantPersonFields from '@/components/telework/aspirant-person-fields';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { show } from '@/routes/tenant/telework/committee';
import { store } from '@/routes/tenant/telework/teleworkers';

/** Registers an active person of the center: who, how much of the time they telework, and where (pantalla: Empleados-Alta). */
export default function RegisterAspirantForm({
    tenantSlug,
    workCenterId,
    states,
}: {
    tenantSlug: string;
    workCenterId: string;
    states: Record<string, string>;
}) {
    const [places, setPlaces] = useState(1);
    const route = { tenant: tenantSlug, workCenter: workCenterId };

    return (
        <Form {...store.form(route)} noValidate className="flex flex-col gap-6">
            {({ processing, errors }) => (
                <>
                    <AspirantPersonFields route={route} errors={errors} />
                    <InputError role="alert" message={errors['work_center']} />
                    {Array.from({ length: places }, (_, index) => (
                        <fieldset
                            key={index}
                            className="grid gap-4 rounded-xl border bg-card p-5"
                        >
                            <legend className="px-1 text-base font-semibold">
                                {places === 1
                                    ? 'Lugar de trabajo'
                                    : `Lugar ${index + 1}`}
                            </legend>
                            <WorkplaceFields
                                index={index}
                                states={states}
                                errors={errors}
                            />
                        </fieldset>
                    ))}
                    <InputError role="alert" message={errors['workplaces']} />
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-[18px]">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPlaces(places + 1)}
                        >
                            <Plus aria-hidden />
                            Agregar otro lugar
                        </Button>
                        <div className="flex gap-2.5">
                            <Button variant="ghost" asChild>
                                <Link href={show(route)}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Registrar persona
                            </Button>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}
