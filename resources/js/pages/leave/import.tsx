import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import ImportForm from '@/components/leave/import-form';
import ImportSteps from '@/components/leave/import-steps';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { importStep } from '@/lib/leave/import';
import { template } from '@/routes/tenant/vacations/import';

/**
 * Loads the days people had pending before the company started using Vacaciones: the file is checked whole first, and
 * written only when confirmed. From the start date on, the system grants the periods itself.
 */
export default function LeaveImport({ maxRows }: { maxRows: number }) {
    const tenantSlug = useTenantSlug();
    const summary = usePage().flash.leave_import;

    setLayoutProps({
        breadcrumbs: [{ title: 'Vacaciones' }, { title: 'Saldos iniciales' }],
    });

    return (
        <>
            <Head title="Cargar saldos iniciales" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Cargar saldos iniciales"
                    description={`Los días que cada persona tenía pendientes antes de arrancar, hasta ${maxRows.toLocaleString('es-MX')} filas. Reimportar el mismo archivo no duplica nada.`}
                >
                    <Button variant="ghost" asChild>
                        <a href={template.url(tenantSlug)} download>
                            <Download />
                            Descargar plantilla
                        </a>
                    </Button>
                </PageHeader>
                <ImportSteps step={importStep(summary)} />
                <ImportForm />
            </div>
        </>
    );
}
