import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/** A table of the dossier: a muted header row and the rows, all of them (it is a document, not a list to page). */
export default function DossierTable({
    columns,
    children,
}: {
    columns: string[];
    children: ReactNode;
}) {
    return (
        <Table className="text-[15px]">
            <TableHeader className="bg-muted/60">
                <TableRow>
                    {columns.map((column) => (
                        <TableHead key={column}>{column}</TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>{children}</TableBody>
        </Table>
    );
}
