import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import BoardSummaryCards from '@/components/disclosure/board-summary';
import CenterCard from '@/components/disclosure/center-card';
import DisclosureTabs from '@/components/disclosure/disclosure-tabs';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { boardHeadline } from '@/lib/disclosure';
import { index as workCenters } from '@/routes/tenant/work-centers';
import type { DisclosureIndexProps } from '@/types';

export default function DisclosureIndex({
    summary,
    centers,
}: DisclosureIndexProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [{ title: 'NOM-035' }, { title: 'Política y difusión' }],
    });

    return (
        <>
            <Head title="Política y difusión" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035"
                    title="Política y difusión"
                    description={`${boardHeadline(summary)} Lo que la norma pide que las personas conozcan: la política, las medidas, cómo quejarse y los resultados (numerales 5.1, 5.7 y 7.8).`}
                />
                <DisclosureTabs active="board" />
                <BoardSummaryCards summary={summary} />
                {centers.total === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="Aún no hay centros de trabajo"
                        action={
                            <Button asChild>
                                <Link href={workCenters(tenantSlug)}>
                                    Agregar un centro
                                </Link>
                            </Button>
                        }
                    >
                        La política y su difusión se llevan por centro de
                        trabajo.
                    </EmptyState>
                ) : (
                    <section
                        aria-label="Centros de trabajo"
                        className="flex flex-col gap-4"
                    >
                        {centers.data.map((center) => (
                            <CenterCard key={center.id} center={center} />
                        ))}
                        <Pagination page={centers} label="Páginas de centros" />
                    </section>
                )}
            </div>
        </>
    );
}
