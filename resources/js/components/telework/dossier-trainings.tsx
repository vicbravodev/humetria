import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import type { DossierOverdue, DossierTraining } from '@/types';

/** 5.9 and 8: the trainings of the last twelve months and the people whose yearly training is overdue. */
export default function DossierTrainings({
    trainings,
    overdue,
}: {
    trainings: DossierTraining[];
    overdue: DossierOverdue[];
}) {
    return (
        <div className="flex flex-col gap-3">
            {trainings.length === 0 ? (
                <DossierMissing>
                    no hay capacitaciones en los últimos 12 meses.
                </DossierMissing>
            ) : (
                <DossierTable
                    columns={['Fecha', 'Modalidad', 'Temas', 'Asistentes']}
                >
                    {trainings.map((training, index) => (
                        <TableRow key={`${training.heldOn}-${index}`}>
                            <TableCell>
                                {describeDate(training.heldOn) ?? '—'}
                            </TableCell>
                            <TableCell>{training.modality}</TableCell>
                            <TableCell className="whitespace-normal">
                                {training.topics.join('; ')}
                            </TableCell>
                            <TableCell>{training.attendees}</TableCell>
                        </TableRow>
                    ))}
                </DossierTable>
            )}
            {overdue.length > 0 && (
                <p className="text-[15px] leading-[22px]">
                    Capacitación vencida:{' '}
                    {overdue
                        .map(
                            (late) =>
                                `${late.person} (desde el ${describeDate(late.dueOn) ?? late.dueOn})`,
                        )
                        .join('; ')}
                    .
                </p>
            )}
        </div>
    );
}
