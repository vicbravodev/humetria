import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import CenterRouteRow from '@/components/seating/center-route-row';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { centersIntro } from '@/lib/seating/centers';
import { dashboard } from '@/routes/tenant';
import { onboarding } from '@/routes/tenant/work-centers';
import type { Paginated, SeatingCenterCard, SeatingSummary } from '@/types';

/** Ley Silla in every center (the pattern of board V2-Centros-N037): who works standing, and the route of each center. */
export default function SeatingIndex({
    centers,
    summary,
    canManage,
}: {
    centers: Paginated<SeatingCenterCard>;
    summary: SeatingSummary;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Panel', href: dashboard(tenantSlug) },
            { title: 'Ley Silla' },
        ],
    });

    return (
        <>
            <Head title="Ley Silla" />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-10 px-4 py-6 md:px-12 md:py-10">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="flex max-w-[760px] flex-col gap-2">
                        <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                            Trabajo de pie en tus centros
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            {centersIntro(summary)}
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild variant="outline">
                            <Link href={onboarding(tenantSlug)}>
                                Revisar otro centro
                            </Link>
                        </Button>
                    )}
                </header>
                <section aria-labelledby="each" className="flex flex-col gap-3">
                    <div>
                        <h2 id="each" className="text-xl font-semibold">
                            En cada centro
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Puestos, asientos, evaluaciones, pausas y reglamento
                            son de cada centro.
                        </p>
                    </div>
                    {centers.total === 0 ? (
                        <EmptyState
                            icon={Building2}
                            title="Sin centros de trabajo"
                        >
                            Ley Silla se lleva por centro de trabajo. Cuando
                            haya uno a tu cargo, aparecerá aquí.
                        </EmptyState>
                    ) : (
                        <div className="@container overflow-hidden card">
                            <div
                                aria-hidden
                                className="hidden grid-cols-[minmax(160px,1.3fr)_80px_minmax(150px,1fr)_minmax(0,1.6fr)_140px] gap-x-4 bg-muted px-5 py-3 text-sm text-muted-foreground @[860px]:grid"
                            >
                                <span>Centro</span>
                                <span>De pie</span>
                                <span>Dónde va</span>
                                <span>Lo siguiente</span>
                                <span />
                            </div>
                            <ul className="[&>li:first-child]:border-t-0 @[860px]:[&>li:first-child]:border-t">
                                {centers.data.map((card) => (
                                    <CenterRouteRow key={card.id} card={card} />
                                ))}
                            </ul>
                        </div>
                    )}
                    <Pagination
                        page={centers}
                        label="Páginas de centros de trabajo"
                        pinned={false}
                    />
                </section>
            </div>
        </>
    );
}
