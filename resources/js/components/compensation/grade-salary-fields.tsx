import FormSection from '@/components/compensation/form-section';
import SalaryDateField from '@/components/compensation/salary-date-field';
import FormField from '@/components/form-field';
import type { TabulatorRow } from '@/types';

/**
 * The fields of a salary of the tabulator: the category and level (typed for a new one, kept for a row of the list) and
 * the daily salary with its vigencia. It starts from the salary in force, since a revision rarely starts from zero.
 */
export default function GradeSalaryFields({
    errors,
    grade,
}: {
    errors: Partial<Record<string, string>>;
    grade: TabulatorRow | undefined;
}) {
    return (
        <FormSection title="Salario nuevo">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="category"
                    idPrefix="salary"
                    label="Categoría"
                    autoComplete="off"
                    defaultValue={grade?.category}
                    readOnly={grade !== undefined}
                    error={errors['category']}
                />
                <FormField
                    name="level"
                    idPrefix="salary"
                    label="Nivel"
                    autoComplete="off"
                    defaultValue={grade?.level}
                    readOnly={grade !== undefined}
                    error={errors['level']}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="daily_salary"
                    idPrefix="salary"
                    label="Salario diario ($)"
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder="Como 524.65"
                    defaultValue={grade?.current?.daily_salary}
                    error={errors['daily_salary']}
                />
                <SalaryDateField
                    idPrefix="salary"
                    error={errors['effective_from']}
                />
            </div>
        </FormSection>
    );
}
