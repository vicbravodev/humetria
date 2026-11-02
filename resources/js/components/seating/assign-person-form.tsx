import { Form } from '@inertiajs/react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { employees } from '@/routes/tenant/seating/positions';
import { store } from '@/routes/tenant/seating/positions/assignments';

/** Puts one more person of the center in the position; the person is searched on the server by name. */
export default function AssignPersonForm({
    positionId,
}: {
    positionId: string;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, position: positionId };

    return (
        <Form
            {...store.form(route)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-wrap items-end gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid min-w-64 flex-1 gap-2">
                        <Label htmlFor="assign-employee">Agregar persona</Label>
                        <Combobox
                            id="assign-employee"
                            name="employee_ids[]"
                            source={serverSource(employees.url(route))}
                            placeholder="Busca por nombre"
                            invalid={errors['employee_ids.0'] !== undefined}
                            describedBy="assign-employee-error"
                        />
                        <InputError
                            id="assign-employee-error"
                            message={
                                errors['employee_ids.0'] ??
                                errors['employee_ids']
                            }
                        />
                    </div>
                    <Button disabled={processing}>
                        {processing && <Spinner />}
                        Asignar
                    </Button>
                </>
            )}
        </Form>
    );
}
