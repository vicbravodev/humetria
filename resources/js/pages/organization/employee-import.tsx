import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import ImportForm from '@/components/organization/import-form';
import ImportInstructions from '@/components/organization/import-instructions';
import ImportSteps from '@/components/organization/import-steps';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { importStep } from '@/lib/employees';
import { index as employees } from '@/routes/tenant/employees';
import { template } from '@/routes/tenant/employees/import';

export default function EmployeeImport({
    maxRows,
    hasWorkCenters,
}: {
    maxRows: number;
    hasWorkCenters: boolean;
}) {
    const page = usePage();
    // Always set here: this page only exists inside a company.
    const tenantSlug = useTenantSlug();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empleados', href: employees(tenantSlug) },
            { title: 'Cargar CSV' },
        ],
    });

    return (
        <>
            <Head title="Cargar empleados" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <div className="flex flex-col gap-[18px]">
                    <PageHeader title="Cargar empleados">
                        <Button variant="ghost" asChild>
                            <a href={template.url(tenantSlug)} download>
                                <Download />
                                Descargar plantilla
                            </a>
                        </Button>
                    </PageHeader>
                    <ImportSteps step={importStep(page.flash.import)} />
                </div>
                <ImportForm
                    aside={
                        <ImportInstructions
                            maxRows={maxRows}
                            hasWorkCenters={hasWorkCenters}
                        />
                    }
                />
            </div>
        </>
    );
}
