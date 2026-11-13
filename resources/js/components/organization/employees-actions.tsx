import { Link, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import CreateEmployeeSheet from '@/components/organization/create-employee-sheet';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { atLimit } from '@/lib/limits';
import { create as importPage } from '@/routes/tenant/employees/import';
import type { EmployeeChoices, PlanLimit } from '@/types';

/**
 * What the header of the employees adds people with: the CSV and the form. Only for who manages the organization, and not
 * in a read-only company; the form is off while the plan has no room for another person.
 */
export default function EmployeesActions({
    choices,
    limit,
}: {
    choices: EmployeeChoices;
    limit: PlanLimit | null;
}) {
    const { tenant } = usePage().props;
    const tenantSlug = useTenantSlug();

    if (tenant?.can.manage_organization !== true) {
        return null;
    }

    return (
        <>
            <Button variant="outline" asChild>
                <Link href={importPage(tenantSlug)}>
                    <Upload />
                    Cargar CSV
                </Link>
            </Button>
            <CreateEmployeeSheet choices={choices} full={atLimit(limit)} />
        </>
    );
}
