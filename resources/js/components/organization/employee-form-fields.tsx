import { Lock } from 'lucide-react';
import FormField from '@/components/form-field';
import EmployeeFormSection from '@/components/organization/employee-form-section';
import EmployeeGenderField from '@/components/organization/employee-gender-field';
import EmployeePlaceFields from '@/components/organization/employee-place-fields';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/** The fields of an employee, in three groups: the person, where they work and how the surveys reach them. */
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

    return (
        <div className="grid gap-7">
            <EmployeeFormSection title="Persona">
                <FormField
                    {...text('name')}
                    label="Nombre completo"
                    required
                    maxLength={200}
                />
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <FormField
                        {...text('job_title')}
                        label="Puesto"
                        required
                        maxLength={150}
                    />
                    <FormField
                        {...text('employee_number')}
                        label="Número de empleado"
                        placeholder="Opcional"
                        maxLength={50}
                    />
                </div>
                <EmployeeGenderField
                    genders={choices.genders}
                    defaultValue={employee?.gender}
                    error={errors['gender']}
                />
            </EmployeeFormSection>
            <EmployeePlaceFields
                errors={errors}
                choices={choices}
                employee={employee}
            />
            <EmployeeFormSection title="Por dónde le llegan las encuestas">
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
                <p className="-mt-2 text-[13px] leading-[18px] text-muted-foreground">
                    Con al menos uno de los dos basta. Hoy el enlace de la
                    encuesta se envía por correo.
                </p>
            </EmployeeFormSection>
            <p className="flex gap-2.5 rounded-[10px] bg-accent px-4 py-3 text-sm leading-5">
                <Lock
                    aria-hidden
                    className="mt-0.5 size-4 shrink-0 text-primary"
                />
                Sus respuestas serán confidenciales: RH solo verá resultados de
                grupos.
            </p>
        </div>
    );
}
