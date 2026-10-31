import DossierMissing from '@/components/telework/dossier-missing';
import DossierTable from '@/components/telework/dossier-table';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import { maritalText } from '@/lib/telework/center';
import type { DossierPerson } from '@/types';

/** 5.1: the people who telework now. Estado civil, teléfono and domicilio appear only when the server sent them. */
export default function DossierRoster({ people }: { people: DossierPerson[] }) {
    if (people.length === 0) {
        return (
            <DossierMissing>nadie está en el padrón vigente.</DossierMissing>
        );
    }

    const personal = people.some(
        (person) => person.maritalStatus !== undefined,
    );

    return (
        <DossierTable
            columns={[
                'Persona',
                'Puesto',
                '% en teletrabajo',
                'Desde',
                ...(personal ? ['Estado civil', 'Teléfono', 'Domicilio'] : []),
            ]}
        >
            {people.map((person, index) => (
                <TableRow key={`${person.name}-${index}`}>
                    <TableCell className="font-medium">{person.name}</TableCell>
                    <TableCell>{person.jobTitle}</TableCell>
                    <TableCell>{person.percentage} %</TableCell>
                    <TableCell>
                        {describeDate(person.startedOn ?? '') ?? '—'}
                    </TableCell>
                    {personal && (
                        <>
                            <TableCell>
                                {maritalText(person.maritalStatus)}
                            </TableCell>
                            <TableCell>{person.contactPhone ?? '—'}</TableCell>
                            <TableCell>{person.homeAddress ?? '—'}</TableCell>
                        </>
                    )}
                </TableRow>
            ))}
        </DossierTable>
    );
}
