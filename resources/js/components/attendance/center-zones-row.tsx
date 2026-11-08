import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overviewQuery } from '@/lib/attendance/overview';
import { center as show, index } from '@/routes/tenant/attendance';
import type { OverviewCenter } from '@/types';

/** A center in use: its people, its zones (none is flagged: nobody can mark there yet) and its screens. */
export default function CenterZonesRow({
    center,
    week,
}: {
    center: OverviewCenter;
    week: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <TableRow>
            <TableCell className="font-medium">{center.name}</TableCell>
            <TableCell className="tabular-nums">{center.people}</TableCell>
            <TableCell>
                {center.zones === 0 ? (
                    <Badge variant="warning">Sin zonas</Badge>
                ) : (
                    <span className="tabular-nums">{center.zones}</span>
                )}
            </TableCell>
            <TableCell className="tabular-nums">{center.screens}</TableCell>
            <TableCell className="text-right whitespace-nowrap">
                <Button asChild variant="ghost" size="sm">
                    <Link
                        href={show({
                            tenant: tenantSlug,
                            workCenter: center.id,
                        })}
                        aria-label={`Zonas y pantallas de ${center.name}`}
                    >
                        Zonas y pantallas
                    </Link>
                </Button>
                <Button asChild variant="ghost" size="sm">
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
            </TableCell>
        </TableRow>
    );
}
