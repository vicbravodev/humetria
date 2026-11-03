import { Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create, show } from '@/routes/tenant/seating/positions';
import type { Paginated, PositionRow } from '@/types';

/** The standing positions of a center with the result of Figura 1 and how many people hold each (pantalla Empleados). */
export default function PositionsList({
    workCenterId,
    positions,
    canManage,
}: {
    workCenterId: string;
    positions: Paginated<PositionRow>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const add = canManage ? (
        <Button asChild>
            <Link href={create([tenantSlug, workCenterId])}>Nuevo puesto</Link>
        </Button>
    ) : undefined;

    return (
        <section aria-label="Puestos de pie" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                    Puestos de pie
                </h2>
                {positions.total > 0 && add}
            </div>
            {positions.total === 0 ? (
                <EmptyState
                    icon={ClipboardList}
                    title="Sin puestos de pie"
                    action={add}
                >
                    Registra los puestos donde se trabaja de pie: por cada uno
                    contestas la Figura 1 y asignas a las personas.
                </EmptyState>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Puesto</TableHead>
                            <TableHead>Bipedestación</TableHead>
                            <TableHead>Asiento</TableHead>
                            <TableHead>Personas</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {positions.data.map((position) => (
                            <TableRow key={position.id}>
                                <TableCell className="font-medium">
                                    <Link
                                        href={show([tenantSlug, position.id])}
                                        className="touch-hit w-fit rounded-sm hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                    >
                                        {position.name}
                                    </Link>
                                </TableCell>
                                <TableCell>{position.typeLabel}</TableCell>
                                <TableCell>
                                    {position.placementLetter === null ? (
                                        'Sin asiento'
                                    ) : (
                                        <Badge variant="secondary">
                                            Resultado {position.placementLetter}
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    {position.people}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
            <Pagination
                page={positions}
                label="Páginas de puestos"
                pinned={false}
            />
        </section>
    );
}
