import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/seating/seats';
import type { SeatRow as Seat } from '@/types';

const LINK =
    'touch-hit rounded-sm text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** One seat of the inventory: what it is, where, its condition, how many characteristics it has, and its photo and edit links. */
export default function SeatRow({
    workCenterId,
    seat,
    canManage,
}: {
    workCenterId: string;
    seat: Seat;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <TableRow>
            <TableCell className="font-medium">
                {seat.quantity} × {seat.kindLabel}
                <span className="block text-caption font-normal text-muted-foreground">
                    {seat.useLabel}
                </span>
            </TableCell>
            <TableCell>
                {seat.positionName ?? seat.location ?? '—'}
                {seat.positionName !== null && seat.location !== null && (
                    <span className="block text-caption text-muted-foreground">
                        {seat.location}
                    </span>
                )}
            </TableCell>
            <TableCell>
                <Badge
                    variant={
                        seat.condition === 'good' ? 'secondary' : 'outline'
                    }
                >
                    {seat.conditionLabel}
                </Badge>
                {seat.signposted && (
                    <span className="ml-2 text-caption text-muted-foreground">
                        Señalizado
                    </span>
                )}
            </TableCell>
            <TableCell className="tabular-nums">{seat.features} de 9</TableCell>
            <TableCell className="text-right whitespace-nowrap">
                {seat.photoHref !== null && (
                    <a
                        href={seat.photoHref}
                        target="_blank"
                        rel="noreferrer"
                        className={`mr-3 ${LINK}`}
                    >
                        Ver foto
                    </a>
                )}
                {canManage && (
                    <Link
                        href={index([tenantSlug, workCenterId], {
                            query: { edit: seat.id },
                        })}
                        className={LINK}
                    >
                        Editar
                        <span className="sr-only"> {seat.kindLabel}</span>
                    </Link>
                )}
            </TableCell>
        </TableRow>
    );
}
