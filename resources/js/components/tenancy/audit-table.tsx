import AuditRow from '@/components/tenancy/audit-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AuditEntryRow } from '@/types';

export default function AuditTable({ entries }: { entries: AuditEntryRow[] }) {
    return (
        <Table className="max-md:block max-md:[&_tbody]:block max-md:[&_thead]:sr-only">
            <TableCaption className="sr-only">
                Bitácora, de lo más reciente a lo más antiguo
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Fecha y hora</TableHead>
                    <TableHead>Evento</TableHead>
                    <TableHead>Quién</TableHead>
                    <TableHead className="hidden md:table-cell">
                        Empresa
                    </TableHead>
                    <TableHead>
                        <span className="sr-only">Detalle</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {entries.map((entry) => (
                    <AuditRow key={entry.id} entry={entry} />
                ))}
            </TableBody>
        </Table>
    );
}
