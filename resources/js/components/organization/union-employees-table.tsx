import EmployeePerson from '@/components/organization/employee-person';
import EmployeeRowActions from '@/components/organization/employee-row-actions';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { catalogOption } from '@/lib/employee-form';
import { describeDate } from '@/lib/calendar-date';
import type { EmployeeChoices, EmployeeRow } from '@/types';

const dash = '—';

/** A union's people: no contact or work center, but where they stand in the pay scale and since when. */
export default function UnionEmployeesTable({
    employees,
    choices,
}: {
    employees: EmployeeRow[];
    choices: EmployeeChoices;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Personas del padrón, por nombre o ID de la empresa
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Categoría y nivel</TableHead>
                    <TableHead>Ingreso</TableHead>
                    <TableHead>Grupo</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {employees.map((employee) => (
                    <TableRow key={employee.id}>
                        <TableCell className="whitespace-normal">
                            <EmployeePerson employee={employee} />
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {employee.job_category === ''
                                ? dash
                                : `${employee.job_category} ${employee.job_level}`}
                        </TableCell>
                        <TableCell className="tabular-nums">
                            {describeDate(employee.hired_on ?? '') ?? dash}
                        </TableCell>
                        <TableCell>
                            {catalogOption(
                                choices.staffGroups,
                                employee.staff_group,
                            )?.label ?? dash}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {employee.area === '' ? dash : employee.area}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            <EmployeeRowActions
                                employee={employee}
                                choices={choices}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
