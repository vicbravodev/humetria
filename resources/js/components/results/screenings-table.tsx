import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ScreenedPerson } from '@/types';

/** Who requires a clinical assessment, by when they answered: names for the record of num. 5.8 c. */
export default function ScreeningsTable({
    people,
}: {
    people: ScreenedPerson[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas que requieren valoración clínica
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>Contestó</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <TableRow key={person.id}>
                        <TableCell className="font-medium whitespace-normal">
                            {person.name}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {person.area}
                        </TableCell>
                        <TableCell className="tabular-nums">
                            {person.answeredOn}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
