import GroupMapFigure from '@/components/engagement/results/group-map-figure';
import { TableCell } from '@/components/ui/table';

/** A cell of the map by group: its figure in a table cell. */
export default function GroupMapCell(props: {
    score: number | null;
    label: string;
}) {
    return (
        <TableCell className="p-1">
            <GroupMapFigure {...props} />
        </TableCell>
    );
}
