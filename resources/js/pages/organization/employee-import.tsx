import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect } from 'react';
import ImportForm from '@/components/organization/import-form';
import ImportInstructions from '@/components/organization/import-instructions';
import ImportSteps from '@/components/organization/import-steps';
import { Button } from '@/components/ui/button';
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
    const tenantSlug = page.props.tenant?.slug ?? '';

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Empleados', href: employees(tenantSlug) },
                { title: 'Cargar CSV' },
            ],
        });
    }, [tenantSlug]);

    return (
        <>
            <Head title="Cargar empleados" />
            <div className="flex max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <header className="flex flex-col gap-[18px]">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                            Cargar empleados
                        </h1>
                        <Button variant="ghost" asChild>
                            <a href={template.url(tenantSlug)} download>
                                <Download />
                                Descargar plantilla
                            </a>
                        </Button>
                    </div>
                    <ImportSteps step={importStep(page.flash.import)} />
                </header>
                <ImportForm
                    tenantSlug={tenantSlug}
                    aside={
                        <ImportInstructions
                            tenantSlug={tenantSlug}
                            maxRows={maxRows}
                            hasWorkCenters={hasWorkCenters}
                        />
                    }
                />
            </div>
        </>
    );
}
