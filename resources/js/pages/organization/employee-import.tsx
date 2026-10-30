import { Head, usePage } from '@inertiajs/react';
import ImportForm from '@/components/organization/import-form';
import ImportInstructions from '@/components/organization/import-instructions';
import PageHeader from '@/components/page-header';

export default function EmployeeImport({
    maxRows,
    hasWorkCenters,
}: {
    maxRows: number;
    hasWorkCenters: boolean;
}) {
    // Always set here: this page only exists inside a company.
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title="Cargar empleados" />
            <div className="flex max-w-3xl flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader caption="Empleados" title="Cargar empleados" />
                <ImportInstructions
                    tenantSlug={tenantSlug}
                    maxRows={maxRows}
                    hasWorkCenters={hasWorkCenters}
                />
                <ImportForm tenantSlug={tenantSlug} />
            </div>
        </>
    );
}
