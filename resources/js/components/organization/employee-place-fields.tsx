import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import EmployeeFormSection from '@/components/organization/employee-form-section';
import { serverSource } from '@/lib/server-options';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/** Where someone works, both searched on the server. An area is chosen by name: one the company lacks is added. */
export default function EmployeePlaceFields({
    errors,
    choices,
    employee,
}: {
    errors: Partial<Record<string, string>>;
    choices: EmployeeChoices;
    employee: EmployeeRow | undefined;
}) {
    const workCenter =
        employee === undefined
            ? undefined
            : { value: employee.work_center_id, label: employee.work_center };
    const area =
        employee === undefined
            ? undefined
            : { value: employee.area, label: employee.area };

    return (
        <EmployeeFormSection title="Dónde trabaja">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <EmployeeChoiceField
                    name="work_center_id"
                    label="Centro de trabajo"
                    source={serverSource(
                        choices.workCentersUrl,
                        workCenter === undefined ? [] : [workCenter],
                    )}
                    defaultValue={workCenter}
                    error={errors['work_center_id']}
                />
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
                    error={errors['area']}
                    allowNew
                />
            </div>
        </EmployeeFormSection>
    );
}
