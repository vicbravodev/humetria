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
import { formatMobile } from '@/lib/employees';
import type { EmployeeChoices, EmployeeRow } from '@/types';

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
                    <TableHead>Empleado</TableHead>
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
                            <p className="font-medium">{employee.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {employee.job_title}
                                {employee.employee_number !== null &&
                                    ` · ${employee.employee_number}`}
                                {employee.deactivated_on !== null &&
                                    ` · Baja el ${employee.deactivated_on}`}
                            </p>
                        </TableCell>
                        <TableCell className="text-sm whitespace-normal">
                            {employee.email !== null && <p>{employee.email}</p>}
                            {employee.mobile !== null && (
                                <p className="tabular-nums">
                                    {formatMobile(employee.mobile)}
                                </p>
                            )}
                        </TableCell>
                        <TableCell className="text-sm whitespace-normal">
                            {employee.work_center}
                        </TableCell>
                        <TableCell className="text-sm whitespace-normal">
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
