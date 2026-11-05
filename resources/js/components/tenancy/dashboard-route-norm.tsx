import { Link } from '@inertiajs/react';
import DashboardRouteRow from '@/components/tenancy/dashboard-route-row';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Norm } from '@/lib/compliance-routes';
import type { RouteProgress } from '@/types';

/** One standard in "Dónde vas": its name, the way to its route and each center where it stands. */
export default function DashboardRouteNorm({
    norm,
    rows,
}: {
    norm: Norm;
    rows: { id: string; name: string; route: RouteProgress }[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div
            data-norm={norm.key}
            className="flex flex-col gap-3.5 border-t px-6 py-5 first:border-t-0"
        >
            <div className="flex items-center justify-between gap-4">
                <div>
                    <h3 className="text-lg font-semibold">{norm.title}</h3>
                    <p className="text-sm text-muted-foreground">
                        {norm.subtitle}
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link
                        href={norm.href(tenantSlug)}
                        aria-label={`Ver ruta de ${norm.title}`}
                    >
                        Ver ruta
                    </Link>
                </Button>
            </div>
            <ul className="flex min-w-0 flex-col gap-2">
                {rows.map((row) => (
                    <DashboardRouteRow
                        key={row.id}
                        name={row.name}
                        route={row.route}
                        answerHref={norm.answer?.(tenantSlug, row.id) ?? null}
                    />
                ))}
            </ul>
        </div>
    );
}
