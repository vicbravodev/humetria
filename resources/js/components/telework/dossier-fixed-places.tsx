import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import { isFixedPlace } from '@/lib/telework/dossier';
import type { DossierWorkplace } from '@/types';

/** 5.1.2: per person, the fixed places in force, with the date and decision of the latest review. */
export default function DossierFixedPlaces({
    workplaces,
}: {
    workplaces: DossierWorkplace[];
}) {
    const fixed = workplaces.filter((place) => isFixedPlace(place.status));

    if (fixed.length === 0) {
        return (
            <DossierMissing>
                ningún lugar de trabajo validado y vigente.
            </DossierMissing>
        );
    }

    return (
        <DossierTable
            columns={['Persona', 'Decisión', 'Revisión', 'Vigente hasta']}
        >
            {fixed.map((place, index) => (
                <TableRow key={`${place.person}-${index}`}>
                    <TableCell className="font-medium">
                        {place.person}
                    </TableCell>
                    <TableCell>{place.review?.decision ?? '—'}</TableCell>
                    <TableCell>
                        {describeDate(place.review?.decidedOn ?? '') ?? '—'}
                    </TableCell>
                    <TableCell>
                        {describeDate(place.review?.validUntil ?? '') ?? '—'}
                    </TableCell>
                </TableRow>
            ))}
        </DossierTable>
    );
}
