import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import type { DossierWorkplace } from '@/types';

/** 5.1.1 and 5.5: each workplace with the date, decision and method of the Comisión's latest review (no notes). */
export default function DossierWorkplaces({
    workplaces,
}: {
    workplaces: DossierWorkplace[];
}) {
    if (workplaces.length === 0) {
        return (
            <DossierMissing>
                no hay lugares de trabajo registrados.
            </DossierMissing>
        );
    }

    return (
        <DossierTable
            columns={[
                'Persona',
                'Situación',
                'Última revisión',
                'Decisión',
                'Método',
                'Vigente hasta',
            ]}
        >
            {workplaces.map((place, index) => (
                <TableRow key={`${place.person}-${index}`}>
                    <TableCell className="font-medium">
                        {place.person}
                    </TableCell>
                    <TableCell>{place.statusLabel}</TableCell>
                    <TableCell>
                        {place.review === null
                            ? 'Falta'
                            : (describeDate(place.review.decidedOn) ?? '—')}
                    </TableCell>
                    <TableCell>{place.review?.decision ?? '—'}</TableCell>
                    <TableCell>{place.review?.method ?? '—'}</TableCell>
                    <TableCell>
                        {describeDate(place.review?.validUntil ?? '') ?? '—'}
                    </TableCell>
                </TableRow>
            ))}
        </DossierTable>
    );
}
