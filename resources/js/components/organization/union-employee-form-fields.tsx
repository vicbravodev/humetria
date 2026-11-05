import FormField from '@/components/form-field';
import EmployeeAreaField from '@/components/organization/employee-area-field';
import EmployeeFormSection from '@/components/organization/employee-form-section';
import EmployeePayScaleFields from '@/components/organization/employee-pay-scale-fields';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/**
 * The fields of a person in a union's roster: the company's ID is all it takes, and the rest is what the contract
 * calculator uses. No work center, contact or gender: the union does not survey anyone.
 */
export default function UnionEmployeeFormFields({
    errors,
    choices,
    employee,
}: {
    errors: Partial<Record<string, string>>;
    choices: EmployeeChoices;
    employee?: EmployeeRow;
}) {
    const text = (name: 'name' | 'job_title' | 'employee_number') => ({
        name,
        idPrefix: 'employee',
        defaultValue: employee?.[name] ?? undefined,
        error: errors[name],
    });

    return (
        <div className="grid gap-7">
            <EmployeeFormSection title="Persona">
                <FormField
                    {...text('employee_number')}
                    label="ID de la empresa"
                    required
                    maxLength={50}
                />
                <p className="-mt-2 text-caption text-muted-foreground">
                    Es el número con el que la empresa identifica a la persona,
                    y con el que se le ubica en el padrón y en el reporte.
                </p>
                <FormField
                    {...text('name')}
                    label="Nombre completo"
                    placeholder="Opcional"
                    maxLength={200}
                />
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <FormField
                        {...text('job_title')}
                        label="Puesto"
                        placeholder="Opcional"
                        maxLength={150}
                    />
                    <EmployeeAreaField
                        error={errors['area']}
                        choices={choices}
                        employee={employee}
                    />
                </div>
            </EmployeeFormSection>
            <EmployeePayScaleFields
                errors={errors}
                choices={choices}
                employee={employee}
            />
        </div>
    );
}
