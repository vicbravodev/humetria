import FormField from '@/components/form-field';
import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import EmployeeGenderField from '@/components/organization/employee-gender-field';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/** The fields of an employee. An area is chosen by name: one the company does not have yet is added. */
export default function EmployeeFormFields({
    errors,
    choices,
    employee,
}: {
    errors: Partial<Record<string, string>>;
    choices: EmployeeChoices;
    employee?: EmployeeRow;
}) {
    const text = (
        name: 'name' | 'email' | 'mobile' | 'job_title' | 'employee_number',
    ) => ({
        name,
        idPrefix: 'employee',
        defaultValue: employee?.[name] ?? undefined,
        error: errors[name],
    });
    const areas = choices.areas.map(({ label }) => ({ value: label, label }));

    return (
        <div className="grid gap-4">
            <FormField
                {...text('name')}
                label="Nombre completo"
                required
                maxLength={200}
            />
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    {...text('email')}
                    label="Correo"
                    type="email"
                    maxLength={255}
                />
                <FormField
                    {...text('mobile')}
                    label="Celular"
                    type="tel"
                    inputMode="tel"
                    maxLength={20}
                />
            </div>
            <p className="-mt-2 text-sm text-muted-foreground">
                Al menos uno de los dos: por ahí le llegan sus encuestas.
            </p>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <EmployeeChoiceField
                    name="work_center_id"
                    label="Centro de trabajo"
                    options={choices.workCenters}
                    defaultValue={choices.workCenters.find(
                        ({ value }) => value === employee?.work_center_id,
                    )}
                    error={errors['work_center_id']}
                />
                <EmployeeChoiceField
                    name="area"
                    label="Área"
                    options={areas}
                    defaultValue={
                        employee === undefined
                            ? undefined
                            : { value: employee.area, label: employee.area }
                    }
                    error={errors['area']}
                    allowNew
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    {...text('job_title')}
                    label="Puesto"
                    required
                    maxLength={150}
                />
                <FormField
                    {...text('employee_number')}
                    label="Número de empleado (opcional)"
                    maxLength={50}
                />
            </div>
            <EmployeeGenderField
                genders={choices.genders}
                defaultValue={employee?.gender}
                error={errors['gender']}
            />
        </div>
    );
}
