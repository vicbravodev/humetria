import { Form } from '@inertiajs/react';
import MeasureFollowUpFields from '@/components/seating/measure-follow-up-fields';
import MeasureNewFields from '@/components/seating/measure-new-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store, update } from '@/routes/tenant/seating/measures';
import type { MeasureEditing, SeatOption } from '@/types';

/** Registers a measure (kind and what it is for) or follows one up (status, owner, due date, evidence). */
export default function MeasureForm({
    workCenterId,
    editing,
    kinds,
    statuses,
    owners,
}: {
    workCenterId: string;
    editing: MeasureEditing | null;
    kinds: SeatOption[];
    statuses: SeatOption[];
    owners: SeatOption[];
}) {
    const tenantSlug = useTenantSlug();
    const action =
        editing === null
            ? store.form([tenantSlug, workCenterId])
            : update.form([tenantSlug, editing.id]);

    return (
        <Form
            key={editing?.id ?? 'new'}
            {...action}
            options={{ preserveScroll: true }}
            resetOnSuccess
            noValidate
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                        {editing === null
                            ? 'Registrar una medida'
                            : `Dar seguimiento: ${editing.kindLabel}`}
                    </h2>
                    {editing === null && (
                        <MeasureNewFields
                            workCenterId={workCenterId}
                            kinds={kinds}
                            errors={errors}
                        />
                    )}
                    <MeasureFollowUpFields
                        editing={editing}
                        statuses={statuses}
                        owners={owners}
                        errors={errors}
                    />
                    <Button className="w-fit" disabled={processing}>
                        {processing && <Spinner />}
                        {editing === null
                            ? 'Registrar medida'
                            : 'Guardar seguimiento'}
                    </Button>
                </>
            )}
        </Form>
    );
}
