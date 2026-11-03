import FormField from '@/components/form-field';
import EmployeeGradeFields from '@/components/organization/employee-grade-fields';
import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import EmployeeFormSection from '@/components/organization/employee-form-section';
import { useIsUnion } from '@/hooks/use-is-union';
import { catalogOption, clearableOptions } from '@/lib/employee-form';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/**
 * What the contract calculator uses of a person, all optional: their category and level in the pay scale (searched on
 * the server; a new one is added), the day they joined, their group and the state where they work.
 */
export default function EmployeePayScaleFields({
    errors,
    choices,
    employee,
}: {
    errors: Partial<Record<string, string>>;
    choices: EmployeeChoices;
    employee: EmployeeRow | undefined;
}) {
    const union = useIsUnion();

    return (
        <EmployeeFormSection title="Tabulador y contrato">
            <EmployeeGradeFields
                errors={errors}
                choices={choices}
                employee={employee}
            />
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="hired_on"
                    idPrefix="employee"
                    label="Fecha de ingreso"
                    type="date"
                    defaultValue={employee?.hired_on ?? undefined}
                    error={errors['hired_on']}
                />
                <EmployeeChoiceField
                    name="staff_group"
                    label="Grupo"
                    placeholder="Opcional"
                    source={{
                        options: clearableOptions(
                            choices.staffGroups,
                            'Sin grupo',
                        ),
                    }}
                    defaultValue={catalogOption(
                        choices.staffGroups,
                        employee?.staff_group ?? '',
                    )}
                    error={errors['staff_group']}
                />
            </div>
            <EmployeeChoiceField
                name="state"
                label="Estado"
                placeholder="Opcional"
                source={{
                    options: clearableOptions(choices.states, 'Sin estado'),
                }}
                defaultValue={catalogOption(
                    choices.states,
                    employee?.state ?? '',
                )}
                error={errors['state']}
            />
            {!union && (
                <p className="-mt-2 text-caption text-muted-foreground">
                    El estado solo hace falta si la persona no tiene centro de
                    trabajo: con uno, se usa el de su centro.
                </p>
            )}
        </EmployeeFormSection>
    );
}
