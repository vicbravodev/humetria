import { Form } from '@inertiajs/react';
import SeatFeaturesFields from '@/components/seating/seat-features-fields';
import SeatStatusFields from '@/components/seating/seat-status-fields';
import SeatWhereFields from '@/components/seating/seat-where-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store, update } from '@/routes/tenant/seating/seats';
import type { SeatEditing, SeatFeatureOption, SeatOption } from '@/types';

/** The form of a seat, new or being edited (pantallas: Empleados-Alta): its sections one under the other. */
export default function SeatForm({
    workCenterId,
    seat,
    kinds,
    uses,
    conditions,
    features,
}: {
    workCenterId: string;
    seat: SeatEditing | null;
    kinds: SeatOption[];
    uses: SeatOption[];
    conditions: SeatOption[];
    features: SeatFeatureOption[];
}) {
    const tenantSlug = useTenantSlug();
    const action =
        seat === null
            ? store.form([tenantSlug, workCenterId])
            : update.form([tenantSlug, seat.id]);

    return (
        <Form
            key={seat?.id ?? 'new'}
            {...action}
            options={{ preserveScroll: true }}
            resetOnSuccess
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <SeatWhereFields
                        workCenterId={workCenterId}
                        seat={seat}
                        kinds={kinds}
                        uses={uses}
                        errors={errors}
                    />
                    <SeatFeaturesFields seat={seat} features={features} />
                    <SeatStatusFields
                        seat={seat}
                        conditions={conditions}
                        errors={errors}
                    />
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            {seat === null
                                ? 'Registrar asiento'
                                : 'Guardar cambios'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
