import Initials from '@/components/humetria/initials';
import { employeeDetails } from '@/lib/employees';
import type { EmployeeRow } from '@/types';

/** A person's cell: initials, their name (or the company ID when a union loaded them without one) and a line of details. */
export default function EmployeePerson({
    employee,
}: {
    employee: EmployeeRow;
}) {
    return (
        <div className="flex items-center gap-3">
            <Initials name={employee.name} />
            <div className="min-w-36">
                <p className="font-medium">{employee.name}</p>
                <p className="text-caption text-muted-foreground">
                    {employeeDetails(employee)}
                </p>
            </div>
        </div>
    );
}
