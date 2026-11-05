import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type ReportColumn<Row> = {
    label: string;
    cell: (row: Row) => ReactNode;
    /** Money and counts line up at the right. */
    right?: boolean;
};

/** A table of the report: its columns say how to read a row, and the money lines up at the right. */
export default function ReportTable<Row>({
    caption,
    columns,
    rows,
    rowKey,
}: {
    caption: string;
    columns: ReportColumn<Row>[];
    rows: Row[];
    rowKey: (row: Row) => string;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">{caption}</TableCaption>
            <TableHeader>
                <TableRow>
                    {columns.map((column) => (
                        <TableHead
                            key={column.label}
                            className={column.right ? 'text-right' : undefined}
                        >
                            {column.label}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={rowKey(row)}>
                        {columns.map((column) => (
                            <TableCell
                                key={column.label}
                                className={
                                    column.right
                                        ? 'text-right tabular-nums'
                                        : 'whitespace-normal'
                                }
                            >
                                {column.cell(row)}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
