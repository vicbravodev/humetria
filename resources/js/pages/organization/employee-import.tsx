import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import ImportForm from '@/components/organization/import-form';
import ImportInstructions from '@/components/organization/import-instructions';
import ImportSteps from '@/components/organization/import-steps';
import UnionImportInstructions from '@/components/organization/union-import-instructions';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { importStep } from '@/lib/employees';
import { index as employees } from '@/routes/tenant/employees';
import { template } from '@/routes/tenant/employees/import';

export default function EmployeeImport({
    maxRows,
    hasWorkCenters,
    defaultCenter,
}: {
    maxRows: number;
    hasWorkCenters: boolean;
    defaultCenter: { id: string; name: string } | null;
}) {
    const page = usePage();
    // Always set here: this page only exists inside a company.
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Empleados', href: employees(tenantSlug) },
            { title: 'Cargar CSV' },
        ],
    });

    return (
        <>
            <Head title={union ? 'Cargar padrón' : 'Cargar empleados'} />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <div className="flex flex-col gap-[18px]">
                    <PageHeader
                        title={union ? 'Cargar padrón' : 'Cargar empleados'}
                    >
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
                    defaultCenter={defaultCenter}
                    aside={
                        union ? (
                            <UnionImportInstructions maxRows={maxRows} />
                        ) : (
                            <ImportInstructions
                                maxRows={maxRows}
                                hasWorkCenters={hasWorkCenters}
                            />
                        )
                    }
                />
            </div>
        </>
    );
}
