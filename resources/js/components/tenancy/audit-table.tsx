import AuditRow from '@/components/tenancy/audit-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { auditDays, auditTag } from '@/lib/audit-view';
import type { AuditEntryRow, AuditFamily } from '@/types';

/** The trail grouped by local day (tablero Bitácora), newest first; wider than a phone, it scrolls sideways there. */
export default function AuditTable({
    entries,
    families,
    chosen,
    onChoose,
}: {
    entries: AuditEntryRow[];
    families: AuditFamily[];
    /** The entry whose detail is docked beside the table, if any. */
    chosen: string | null;
    onChoose: (entry: AuditEntryRow) => void;
}) {
    return (
        <Table className="min-w-[640px] text-sm">
            <TableCaption className="sr-only">
                Bitácora por día, de lo más reciente a lo más antiguo
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Hora</TableHead>
                    <TableHead>Quién</TableHead>
                    <TableHead>Acción</TableHead>
                    <TableHead>Empresa</TableHead>
                    <TableHead>Origen</TableHead>
                </TableRow>
            </TableHeader>
            {auditDays(entries).map(({ day, entries: rows }) => (
                <TableBody key={day} className="[&_tr:last-child]:border-b">
                    <tr data-slot="table-day">
                        <th
                            scope="rowgroup"
                            colSpan={5}
                            className="border-b bg-muted px-4 py-2 text-left text-caption font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {day}
                        </th>
                    </tr>
                    {rows.map((entry) => (
                        <AuditRow
                            key={entry.id}
                            entry={entry}
                            tag={auditTag(entry, families)}
                            chosen={entry.id === chosen}
                            onChoose={() => onChoose(entry)}
                        />
                    ))}
                </TableBody>
            ))}
        </Table>
    );
}
