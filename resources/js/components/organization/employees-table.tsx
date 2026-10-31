import Initials from '@/components/humetria/initials';
import EmployeeContact from '@/components/organization/employee-contact';
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

/** One line under the name: the position, the number and, for someone deactivated, since when. */
const details = (employee: EmployeeRow) =>
    [
        employee.job_title,
        employee.employee_number,
        employee.deactivated_on === null
            ? null
            : `Baja el ${employee.deactivated_on}`,
    ]
        .filter((part) => part !== null)
        .join(' · ');

export default function EmployeesTable({
    tenantSlug,
    employees,
    choices,
}: {
    tenantSlug: string;
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
                            <div className="flex items-center gap-3">
                                <Initials name={employee.name} />
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {employee.name}
                                    </p>
                                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                                        {details(employee)}
                                    </p>
                                </div>
                            </div>
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
                                tenantSlug={tenantSlug}
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
