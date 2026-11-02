import { Form, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show, store } from '@/routes/tenant/seating/assessments';
import type { AssessmentPerson } from '@/types';

/** What can be done with a person's assessment: open the one that exists, or start the next one if the Comisión assesses. */
export default function AssessmentRowAction({
    workCenterId,
    person,
    canAssess,
}: {
    workCenterId: string;
    person: AssessmentPerson;
    canAssess: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const label = {
        none: 'Evaluar',
        draft: 'Continuar',
        due: 'Evaluar de nuevo',
        signed: 'Ver',
    }[person.state];

    if (person.state === 'draft' || !canAssess) {
        return person.assessmentId === null ? null : (
            <Button variant="outline" size="sm" asChild>
                <Link href={show([tenantSlug, person.assessmentId])}>
                    {label}
                    <span className="sr-only"> a {person.name}</span>
                </Link>
            </Button>
        );
    }

    return (
        <Form {...store.form([tenantSlug, workCenterId])}>
            {({ processing }) => (
                <>
                    <input
                        type="hidden"
                        name="employee_id"
                        value={person.employeeId}
                    />
                    <Button size="sm" disabled={processing}>
                        {processing && <Spinner />}
                        {label}
                        <span className="sr-only"> a {person.name}</span>
                    </Button>
                </>
            )}
        </Form>
    );
}
