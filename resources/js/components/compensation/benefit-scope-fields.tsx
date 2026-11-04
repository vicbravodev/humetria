import BenefitGradesField from '@/components/compensation/benefit-grades-field';
import ChoiceField from '@/components/compensation/choice-field';
import FormSection from '@/components/compensation/form-section';
import FormField from '@/components/form-field';
import { clearableOptions } from '@/lib/employee-form';
import type { BenefitChoices, BenefitRow } from '@/types';

/** Who a benefit is for: a group, some categories of the pay scale, and the months of service it takes. */
export default function BenefitScopeFields({
    errors,
    choices,
    benefit,
}: {
    errors: Partial<Record<string, string>>;
    choices: BenefitChoices;
    benefit: BenefitRow | undefined;
}) {
    const groups = clearableOptions(choices.groups, 'Todos');

    return (
        <FormSection title="A quién aplica">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <ChoiceField
                    name="group"
                    label="Grupo"
                    source={{ options: groups }}
                    defaultValue={groups.find(
                        (option) => option.value === (benefit?.group ?? ''),
                    )}
                    error={errors['group']}
                />
                <FormField
                    name="min_tenure_months"
                    idPrefix="benefit"
                    label="Antigüedad mínima (meses)"
                    type="number"
                    min={0}
                    max={1200}
                    defaultValue={benefit?.min_tenure_months ?? 0}
                    error={errors['min_tenure_months']}
                />
            </div>
            <BenefitGradesField
                gradesUrl={choices.gradesUrl}
                initial={benefit?.grades ?? []}
                error={errors['job_grade_ids']}
            />
        </FormSection>
    );
}
