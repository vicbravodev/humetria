import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ClimateUnreachable, Paginated } from '@/types';

/** The people with no email, by name and area, a page at a time: they will not receive their link until they have one. */
export default function UnreachableList({
    people,
}: {
    people: Paginated<ClimateUnreachable>;
}) {
    return (
        <div className="flex flex-col gap-3">
            <Table>
                <TableCaption className="sr-only">
                    Personas sin correo
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Persona</TableHead>
                        <TableHead>Área</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {people.data.map((person) => (
                        <TableRow key={person.id}>
                            <TableCell className="font-medium">
                                {person.name}
                            </TableCell>
                            <TableCell>{person.area}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <Pagination
                page={people}
                label="Páginas de personas sin correo"
                pinned={false}
                preserveState
            />
        </div>
    );
}
