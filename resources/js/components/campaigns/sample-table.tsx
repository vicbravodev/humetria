import Initials from '@/components/humetria/initials';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { SampledPerson } from '@/types';

/** Who the draw put in the Guía III sample. Everyone else still answers Guía I. */
export default function SampleTable({ people }: { people: SampledPerson[] }) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas en la muestra, por nombre
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>Género</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <TableRow key={person.id}>
                        <TableCell>
                            <span className="flex items-center gap-2.5 font-medium">
                                <Initials name={person.name} />
                                {person.name}
                            </span>
                        </TableCell>
                        <TableCell className="text-sm">{person.area}</TableCell>
                        <TableCell className="text-sm">
                            {person.gender}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
