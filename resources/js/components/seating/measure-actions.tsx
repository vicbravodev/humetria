import { Form, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { plan } from '@/routes/tenant/seating';
import { cancel } from '@/routes/tenant/seating/measures';
import type { MeasureRow } from '@/types';

export const LINK =
    'touch-hit rounded-sm text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** What RH does with an open measure: follow it up (opens it in the form) or cancel it. */
export default function MeasureActions({
    workCenterId,
    measure,
}: {
    workCenterId: string;
    measure: MeasureRow;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Link
                href={plan([tenantSlug, workCenterId], {
                    query: { edit: measure.id },
                })}
                className={`mr-2 ${LINK}`}
            >
                Dar seguimiento
                <span className="sr-only"> a {measure.kindLabel}</span>
            </Link>
            <Form
                {...cancel.form([tenantSlug, measure.id])}
                options={{ preserveScroll: true }}
                className="inline"
            >
                {({ processing }) => (
                    <Button variant="ghost" size="sm" disabled={processing}>
                        Cancelar
                        <span className="sr-only"> {measure.kindLabel}</span>
                    </Button>
                )}
            </Form>
        </>
    );
}
