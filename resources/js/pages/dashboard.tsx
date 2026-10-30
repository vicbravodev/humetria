import { Head, Link, usePage } from '@inertiajs/react';
import { ClipboardList, Factory } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as workCenters } from '@/routes/tenant/work-centers';

export default function Dashboard({
    hasWorkCenters,
}: {
    hasWorkCenters: boolean;
}) {
    // Always set here: this page only exists inside a company.
    const tenantSlug = usePage().props.tenant?.slug ?? '';

    return (
        <>
            <Head title="Panel" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Panel" />
                {hasWorkCenters ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Aún no hay campañas"
                    >
                        Cuando lances la primera campaña, aquí verás su avance.
                    </EmptyState>
                ) : (
                    <EmptyState
                        icon={Factory}
                        title="Empieza por tus centros de trabajo"
                    >
                        Cada evaluación se hace por centro de trabajo. Da de
                        alta el primero para empezar.
                        <Button asChild className="mx-auto mt-6 block w-fit">
                            <Link href={workCenters(tenantSlug)}>
                                Ir a centros de trabajo
                            </Link>
                        </Button>
                    </EmptyState>
                )}
            </div>
        </>
    );
}
