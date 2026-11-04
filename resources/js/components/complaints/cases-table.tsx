import { Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import CaseStateBadge from '@/components/complaints/case-state-badge';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { show } from '@/routes/tenant/complaints/cases';
import type { CaseRow, Paginated } from '@/types';

/** The cases assigned to the person: what each is (reference, type, day, center), never what it says. */
export default function CasesTable({
    cases,
    closed,
}: {
    cases: Paginated<CaseRow>;
    closed: boolean;
}) {
    const tenantSlug = useTenantSlug();

    if (cases.total === 0) {
        return (
            <EmptyState
                icon={Inbox}
                title={closed ? 'Sin casos cerrados' : 'Sin casos abiertos'}
            >
                {closed
                    ? 'Los casos que cierres quedan aquí, sin su contenido, como constancia de que el canal opera.'
                    : 'Cuando alguien presente una queja y te la asignen, aparecerá aquí. No hay correo por cada una: el resumen llega cada hora, solo con conteos.'}
            </EmptyState>
        );
    }

    return (
        <>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Referencia</TableHead>
                        <TableHead>Qué es</TableHead>
                        <TableHead>Recibida</TableHead>
                        <TableHead>Centro</TableHead>
                        <TableHead>Estado</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {cases.data.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={show.url({
                                        tenant: tenantSlug,
                                        complaint: row.id,
                                    })}
                                    className="touch-hit rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {row.reference}
                                </Link>
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                {row.typeLabel}
                                <span className="block text-caption text-muted-foreground">
                                    Contra: {row.kindLabel}
                                </span>
                            </TableCell>
                            <TableCell>
                                {describeDate(row.receivedOn) ?? row.receivedOn}
                            </TableCell>
                            <TableCell>{row.center ?? '—'}</TableCell>
                            <TableCell>
                                <CaseStateBadge
                                    status={row.status}
                                    label={row.resolution ?? row.statusLabel}
                                    overdue={row.overdue}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination page={cases} label="Páginas de casos" pinned={false} />
        </>
    );
}
