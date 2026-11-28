import { Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { kindTone, stateTone } from '@/lib/announcements';
import type { AnnouncementRow } from '@/types';

/** One announcement in the table: what it says, its kind, to whom, its start and end on two lines, its state and «Editar». */
export default function AnnouncementTableRow({
    row,
    onEdit,
}: {
    row: AnnouncementRow;
    onEdit: (announcement: AnnouncementRow) => void;
}) {
    return (
        <TableRow>
            <TableCell className="min-w-28 whitespace-normal">
                <div className="font-medium">{row.title}</div>
                <div className="line-clamp-2 text-meta text-muted-foreground">
                    {row.message}
                </div>
            </TableCell>
            <TableCell>
                <Badge variant={kindTone(row.kind)}>{row.kind_label}</Badge>
            </TableCell>
            <TableCell className="min-w-24 whitespace-normal">
                {row.audience_label}
            </TableCell>
            <TableCell className="text-meta text-muted-foreground tabular-nums">
                {row.when.split(' – ').map((moment, at) => (
                    <div key={moment}>{at === 0 ? moment : `– ${moment}`}</div>
                ))}
            </TableCell>
            <TableCell>
                <Badge variant={stateTone(row.state)}>{row.state_label}</Badge>
            </TableCell>
            <TableCell className="text-right">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-11"
                    onClick={() => onEdit(row)}
                    aria-label={`Editar «${row.title}»`}
                    title="Editar"
                >
                    <Pencil aria-hidden />
                </Button>
            </TableCell>
        </TableRow>
    );
}
