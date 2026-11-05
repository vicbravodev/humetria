import { Head, Link } from '@inertiajs/react';
import { Calculator } from 'lucide-react';
import SetupLinks from '@/components/compensation/setup-links';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create as importPage } from '@/routes/tenant/employees/import';

export default function CompensationIndex() {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title="Compensación" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Compensación"
                    description="Costea una propuesta de revisión de contrato colectivo: salario y prestaciones, a corto y largo plazo, con todos sus supuestos."
                />
                <SetupLinks />
                <EmptyState
                    icon={Calculator}
                    title="Todavía no hay nada que costear"
                    action={
                        <Button asChild>
                            <Link href={importPage(tenantSlug)}>
                                Cargar el padrón
                            </Link>
                        </Button>
                    }
                >
                    Empieza por el padrón con la categoría y el nivel de cada
                    persona. Después podrás capturar el tabulador y armar tus
                    escenarios.
                </EmptyState>
            </div>
        </>
    );
}

CompensationIndex.layout = {
    breadcrumbs: [{ title: 'Herramientas' }, { title: 'Compensación' }],
};
