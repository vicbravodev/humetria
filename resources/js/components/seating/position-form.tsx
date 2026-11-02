import { Form } from '@inertiajs/react';
import AreaFields from '@/components/seating/area-fields';
import ConditionDefaultsFields from '@/components/seating/condition-defaults-fields';
import FigureOneFields from '@/components/seating/figure-one-fields';
import NatureFields from '@/components/seating/nature-fields';
import PositionBasicsFields from '@/components/seating/position-basics-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store, update } from '@/routes/tenant/seating/positions';
import type { ConditionOption, StandingPosition, TypeOption } from '@/types';

/** The form of a standing position, new or saved (pantallas: Empleados-Alta): its sections one under the other. */
export default function PositionForm({
    workCenterId,
    position,
    types,
    conditions,
    industrial,
}: {
    workCenterId: string;
    position: StandingPosition | null;
    types: TypeOption[];
    conditions: ConditionOption[];
    industrial: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const action =
        position === null
            ? store.form([tenantSlug, workCenterId])
            : update.form([tenantSlug, position.id]);

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <PositionBasicsFields
                        position={position}
                        types={types}
                        errors={errors}
                    />
                    <FigureOneFields position={position} errors={errors} />
                    {industrial && (
                        <NatureFields position={position} errors={errors} />
                    )}
                    <AreaFields position={position} errors={errors} />
                    <ConditionDefaultsFields
                        conditions={conditions}
                        position={position}
                        errors={errors}
                    />
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Guardar puesto
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
