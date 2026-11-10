import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { centerFacts, centerState } from '@/lib/attendance/center-state';
import { overviewQuery } from '@/lib/attendance/overview';
import { center as show, index } from '@/routes/tenant/attendance';
import type { OverviewCenter } from '@/types';

/**
 * A center in use (board A1 «Centros y zonas»): its people, zones and active screens, whether its people can mark in it and,
 * when they cannot, why and the shortcut to what it lacks first.
 */
export default function CenterCard({
    center,
    week,
    requiresQr,
}: {
    center: OverviewCenter;
    week: string;
    requiresQr: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const state = centerState(center, requiresQr);
    const page = show({ tenant: tenantSlug, workCenter: center.id });

    return (
        <article className="flex min-w-0 flex-col gap-3.5 card p-5">
            <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h3 className="text-lg leading-[26px] font-semibold">
                        {center.name}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {centerFacts(center)}
                    </p>
                </div>
                <Badge variant={state.variant}>{state.label}</Badge>
            </div>
            {state.problem !== null && <Notice>{state.problem}</Notice>}
            <div className="flex flex-wrap gap-2.5">
                {state.action !== null && (
                    <Button asChild>
                        <Link href={page}>{state.action}</Link>
                    </Button>
                )}
                <Button asChild variant="outline">
                    <Link
                        href={page}
                        aria-label={`Zonas y pantallas de ${center.name}`}
                    >
                        Zonas y pantallas
                    </Link>
                </Button>
                <Button asChild variant="outline">
                    <Link
                        href={index(tenantSlug, {
                            query: overviewQuery({
                                q: '',
                                status: 'all',
                                week,
                                center: center.id,
                            }),
                        })}
                        aria-label={`Ver la semana de ${center.name}`}
                    >
                        Ver su semana
                    </Link>
                </Button>
            </div>
        </article>
    );
}
