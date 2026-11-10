import EmployeeAreaField from '@/components/organization/employee-area-field';
import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import EmployeeManagerField from '@/components/organization/employee-manager-field';
import EmployeeFormSection from '@/components/organization/employee-form-section';
import { serverSource } from '@/lib/server-options';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/**
 * Where someone works and whom they report to, all searched on the server. An area is chosen by name: one the company
 * lacks is added.
 */
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
                <EmployeeAreaField
                    error={errors['area']}
                    choices={choices}
                    employee={employee}
                />
            </div>
            <EmployeeManagerField
                error={errors['manager_id']}
                choices={choices}
                except={employee?.id}
                manager={employee?.manager ?? null}
            />
        </EmployeeFormSection>
    );
}
