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
                    <TableHead>Nombre</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>Género</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <TableRow key={person.id}>
                        <TableCell className="font-medium">
                            {person.name}
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
