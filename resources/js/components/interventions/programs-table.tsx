import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { meterText, stateVariant } from '@/lib/interventions';
import { program } from '@/routes/tenant/campaigns';
import type { ProgramListRow } from '@/types';

/** The closed evaluations with where their Programa stands (pantalla NOM035, Historial): a page at a time. */
export default function ProgramsTable({ rows }: { rows: ProgramListRow[] }) {
    const tenantSlug = useTenantSlug();

    return (
        <Table>
            <TableCaption className="sr-only">
                Programas de intervención por evaluación
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Cerrada el</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Acciones</TableHead>
                    <TableHead>
                        <span className="sr-only">Programa</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium whitespace-normal">
                            {row.workCenter}
                        </TableCell>
                        <TableCell>{row.closedOn}</TableCell>
                        <TableCell>
                            {row.program === null ? (
                                <Badge variant="outline">
                                    {row.applies ? 'Sin Programa' : 'No aplica'}
                                </Badge>
                            ) : (
                                <Badge
                                    variant={stateVariant(
                                        row.program.state.value,
                                    )}
                                >
                                    {row.program.state.label}
                                </Badge>
                            )}
                        </TableCell>
                        <TableCell>
                            {row.program === null
                                ? '—'
                                : meterText(row.program.meter)}
                        </TableCell>
                        <TableCell className="text-right">
                            {row.applies && (
                                <Link
                                    href={program([tenantSlug, row.id])}
                                    className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Programa
                                    <span className="sr-only">
                                        {' '}
                                        de {row.workCenter}, {row.closedOn}
                                    </span>
                                </Link>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
