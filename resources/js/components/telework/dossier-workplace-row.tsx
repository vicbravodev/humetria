import { TableCell, TableRow } from '@/components/ui/table';
import { reviewCells } from '@/lib/telework/dossier';
import type { DossierWorkplace } from '@/types';

/** One workplace of the dossier: who, where it stands and the Comisión's latest decision with its count and note. */
export default function DossierWorkplaceRow({
    place,
}: {
    place: DossierWorkplace;
}) {
    const cells = reviewCells(place.review);

    return (
        <TableRow>
            <TableCell className="font-medium">{place.person}</TableCell>
            <TableCell>{place.statusLabel}</TableCell>
            <TableCell>{cells.decidedOn}</TableCell>
            <TableCell>{cells.decision}</TableCell>
            <TableCell>{cells.method}</TableCell>
            <TableCell>{cells.validUntil}</TableCell>
            <TableCell>{cells.unfavorable}</TableCell>
            <TableCell className="max-w-64 whitespace-normal">
                {cells.note}
            </TableCell>
        </TableRow>
    );
}
