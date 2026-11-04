import GradeCurrentSalary from '@/components/compensation/grade-current-salary';
import OverridePerson from '@/components/compensation/override-person';
import OverrideRowAction from '@/components/compensation/override-row-action';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import type { OverrideRow } from '@/types';

/** The people with an individual salary: the one in force today, why, and how many vigencias they have had. */
export default function OverridesTable({ people }: { people: OverrideRow[] }) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas con sueldo individual, por ID de la empresa
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Salario diario hoy</TableHead>
                    <TableHead>Nota</TableHead>
                    <TableHead>Vigencias</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {people.map((person) => (
                    <TableRow key={person.employee_number}>
                        <TableCell className="whitespace-normal">
                            <OverridePerson person={person} />
                        </TableCell>
                        <TableCell>
                            <GradeCurrentSalary current={person.current} />
                        </TableCell>
                        <TableCell className="max-w-64 whitespace-normal text-muted-foreground">
                            {person.current?.note ?? '—'}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {countOf(
                                person.salaries.length,
                                'vigencia',
                                'vigencias',
                            )}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            <OverrideRowAction person={person} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
