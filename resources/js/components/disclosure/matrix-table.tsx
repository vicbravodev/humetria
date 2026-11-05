import { Link } from '@inertiajs/react';
import MatrixCell from '@/components/disclosure/matrix-cell';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { BoardCenter, MatrixItem } from '@/types';

/**
 * The six things the people must know × the centers (board V4-Difusion), instead of six tabs: each row with its numeral, what
 * it is or where it stands, a state per center and its way in. Wider than a phone, it scrolls inside its frame.
 */
export default function MatrixTable({
    items,
    centers,
}: {
    items: MatrixItem[];
    centers: BoardCenter[];
}) {
    return (
        <Table aria-label="Lo que deben conocer, por centro">
            <TableHeader>
                <TableRow>
                    <TableHead className="min-w-[220px] md:min-w-[280px]">
                        Qué deben conocer
                    </TableHead>
                    {centers.map((center) => (
                        <TableHead
                            key={center.id}
                            className="min-w-[112px] text-center whitespace-normal"
                        >
                            {center.name}
                        </TableHead>
                    ))}
                    <TableHead className="text-right">
                        <span className="sr-only">Qué hacer</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {items.map((item) => (
                    <TableRow key={item.key}>
                        <TableCell className="whitespace-normal">
                            <p className="flex flex-wrap items-baseline gap-x-2">
                                <span className="font-semibold">
                                    {item.label}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {item.numeral}
                                </span>
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {item.caption}
                            </p>
                        </TableCell>
                        {centers.map((center) => (
                            <TableCell key={center.id} className="text-center">
                                <MatrixCell
                                    state={center.cells[item.key] ?? 'missing'}
                                    draft={item.draft}
                                />
                            </TableCell>
                        ))}
                        <TableCell className="text-right">
                            {item.action !== null && (
                                <Button
                                    asChild
                                    size="sm"
                                    variant={
                                        item.action.primary
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    <Link href={item.action.href}>
                                        {item.action.label}
                                    </Link>
                                </Button>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
