import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import { namedOption, namedSource } from '@/lib/employee-form';
import type { EmployeeChoices, EmployeeRow } from '@/types';

/** The category and level of the pay scale, side by side: both searched on the server, and a new one is added. */
export default function EmployeeGradeFields({
    errors,
    choices,
    employee,
}: {
    errors: Partial<Record<string, string>>;
    choices: EmployeeChoices;
    employee: EmployeeRow | undefined;
}) {
    const category = namedOption(employee?.job_category ?? '');
    const level = namedOption(employee?.job_level ?? '');

    return (
        <div className="grid items-start gap-4 sm:grid-cols-2">
            <EmployeeChoiceField
                name="job_category"
                label="Categoría"
                placeholder="Opcional"
                source={namedSource(
                    choices.jobCategoriesUrl,
                    category,
                    'Sin categoría',
                )}
                defaultValue={category}
                error={errors['job_category']}
                allowNew
            />
            <EmployeeChoiceField
                name="job_level"
                label="Nivel"
                placeholder="Opcional"
                source={namedSource(choices.jobLevelsUrl, level, 'Sin nivel')}
                defaultValue={level}
                error={errors['job_level']}
                allowNew
            />
        </div>
    );
}
