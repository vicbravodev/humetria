import EmployeeContact from '@/components/organization/employee-contact';
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
import type { EmployeeChoices, EmployeeRow } from '@/types';

export default function EmployeesTable({
    employees,
    choices,
}: {
    employees: EmployeeRow[];
    choices: EmployeeChoices;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Empleados, por nombre
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Contacto</TableHead>
                    <TableHead>Centro de trabajo</TableHead>
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
                        <TableCell className="max-w-72">
                            <EmployeeContact employee={employee} />
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {employee.work_center}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {employee.area}
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
