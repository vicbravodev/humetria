import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import type { DossierEquipment } from '@/types';

/** 5.7: per person, the items received, refused and waiting for the person's answer (never the reason for a refusal). */
export default function DossierEquipmentBlock({
    equipment,
}: {
    equipment: DossierEquipment[];
}) {
    if (equipment.length === 0) {
        return (
            <DossierMissing>
                nadie en el padrón tiene equipo registrado.
            </DossierMissing>
        );
    }

    return (
        <DossierTable
            columns={[
                'Persona',
                'Recibido',
                'Rechazado',
                'Por confirmar',
                'Última entrega',
            ]}
        >
            {equipment.map((item, index) => (
                <TableRow key={`${item.person}-${index}`}>
                    <TableCell className="font-medium">{item.person}</TableCell>
                    <TableCell>{item.received}</TableCell>
                    <TableCell>{item.refused}</TableCell>
                    <TableCell>{item.pending}</TableCell>
                    <TableCell>
                        {describeDate(item.lastDeliveredOn ?? '') ??
                            (item.justified
                                ? 'Sin equipo, justificado'
                                : 'Falta')}
                    </TableCell>
                </TableRow>
            ))}
        </DossierTable>
    );
}
