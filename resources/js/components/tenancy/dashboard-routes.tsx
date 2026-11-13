import { Link, usePage } from '@inertiajs/react';
import DashboardRouteNorm from '@/components/tenancy/dashboard-route-norm';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { normsInUse } from '@/lib/dashboard';
import { index } from '@/routes/tenant/work-centers';
import type { CenterRoutes, Paginated } from '@/types';

/** "Dónde vas" (board V2-Panel): each standard that applies, with the first five centers and its six stages; «Ver todos» goes to the whole list. */
export default function DashboardRoutes({
    centers,
}: {
    centers: Paginated<CenterRoutes>;
}) {
    const modules = usePage().props.tenant?.modules;
    const tenantSlug = useTenantSlug();

    return (
        <section aria-labelledby="routes" className="flex flex-col gap-3">
            <h2 id="routes" className="text-xl leading-7 font-semibold">
                Dónde vas
            </h2>
            <div className="@container overflow-hidden card">
                {normsInUse(centers.data, modules).map(({ norm, rows }) => (
                    <DashboardRouteNorm
                        key={norm.key}
                        norm={norm}
                        rows={rows}
                    />
                ))}
                {centers.total > centers.data.length && (
                    <div className="flex items-center justify-between gap-4 border-t px-6 py-3">
                        <p className="text-sm text-muted-foreground">
                            Aquí van {centers.data.length} de {centers.total}{' '}
                            centros.
                        </p>
                        <Button asChild variant="outline">
                            <Link href={index(tenantSlug)}>Ver todos</Link>
                        </Button>
                    </div>
                )}
            </div>
        </section>
    );
}
