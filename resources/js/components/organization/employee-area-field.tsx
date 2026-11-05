import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import { serverSource } from '@/lib/server-options';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/** The area, searched on the server and chosen by name: one the company lacks is added. */
export default function EmployeeAreaField({
    error,
    choices,
    employee,
}: {
    error: string | undefined;
    choices: EmployeeChoices;
    employee: EmployeeRow | undefined;
}) {
    const area =
        employee === undefined || employee.area === ''
            ? undefined
            : { value: employee.area, label: employee.area };

    return (
        <EmployeeChoiceField
            name="area"
            label="Área"
            // An area is sent by name: one that does not exist yet is added.
            source={serverSource(
                choices.areasUrl,
                area === undefined ? [] : [area],
                { allowNew: true, byName: true },
            )}
            defaultValue={area}
            error={error}
            allowNew
        />
    );
}
