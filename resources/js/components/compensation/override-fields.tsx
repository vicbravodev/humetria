import FormSection from '@/components/compensation/form-section';
import SalaryDateField from '@/components/compensation/salary-date-field';
import FormField from '@/components/form-field';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/humetria/input-error';
import type { OverrideRow } from '@/types';

/**
 * The fields of an individual salary: the person (typed for a new one, kept for a row of the list), what they earn per
 * day, from when, and why it is not their category's. It starts from the salary in force.
 */
export default function OverrideFields({
    errors,
    person,
}: {
    errors: Partial<Record<string, string>>;
    person: OverrideRow | undefined;
}) {
    return (
        <FormSection title="Sueldo nuevo">
            <FormField
                name="employee_number"
                idPrefix="override"
                label="ID de la empresa"
                autoComplete="off"
                defaultValue={person?.employee_number}
                readOnly={person !== undefined}
                error={errors['employee_number']}
            />
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="daily_salary"
                    idPrefix="override"
                    label="Salario diario ($)"
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder="Como 650.25"
                    defaultValue={person?.current?.daily_salary}
                    error={errors['daily_salary']}
                />
                <SalaryDateField
                    idPrefix="override"
                    error={errors['effective_from']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="override-note">Nota (opcional)</Label>
                <Textarea
                    id="override-note"
                    name="note"
                    rows={2}
                    defaultValue={person?.current?.note ?? ''}
                    aria-invalid={
                        errors['note'] === undefined ? undefined : true
                    }
                    aria-describedby={
                        errors['note'] === undefined
                            ? undefined
                            : 'override-note-error'
                    }
                />
                <InputError id="override-note-error" message={errors['note']} />
            </div>
        </FormSection>
    );
}
