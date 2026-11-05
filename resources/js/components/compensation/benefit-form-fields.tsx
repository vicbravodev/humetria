import { useState } from 'react';
import BenefitBasesFields from '@/components/compensation/benefit-bases-fields';
import BenefitScopeFields from '@/components/compensation/benefit-scope-fields';
import BenefitValueFields from '@/components/compensation/benefit-value-fields';
import ChoiceField from '@/components/compensation/choice-field';
import FormSection from '@/components/compensation/form-section';
import FormField from '@/components/form-field';
import type { BenefitChoices, BenefitRow, ChoiceOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/**
 * The attributes of a benefit the company makes up, as a form and not code: how it is worked out, who gets it and
 * which bases it forms part of. A new one also asks for its first value.
 */
export default function BenefitFormFields({
    errors,
    choices,
    benefit,
}: {
    errors: Errors;
    choices: BenefitChoices;
    benefit?: BenefitRow;
}) {
    const [kind, setKind] = useState(benefit?.kind ?? 'salary_days');
    const unit = choices.kinds.find((option) => option.value === kind)?.unit;
    const chosen = (options: ChoiceOption[], value: string) =>
        options.find((option) => option.value === value);

    return (
        <>
            <FormSection title="Qué es">
                <FormField
                    name="name"
                    idPrefix="benefit"
                    label="Nombre"
                    maxLength={120}
                    defaultValue={benefit?.name}
                    error={errors['name']}
                />
                <div className="grid items-start gap-4 sm:grid-cols-2">
                    <ChoiceField
                        name="kind"
                        label="Cómo se calcula"
                        source={{ options: choices.kinds }}
                        defaultValue={chosen(choices.kinds, kind)}
                        error={errors['kind']}
                        onChange={(option) => setKind(option.value)}
                    />
                    <ChoiceField
                        name="frequency"
                        label="Cada cuándo se paga"
                        source={{ options: choices.frequencies }}
                        defaultValue={chosen(
                            choices.frequencies,
                            benefit?.frequency ?? 'yearly',
                        )}
                        error={errors['frequency']}
                    />
                </div>
            </FormSection>
            {benefit === undefined && (
                <BenefitValueFields errors={errors} unit={unit} />
            )}
            <BenefitScopeFields
                errors={errors}
                choices={choices}
                benefit={benefit}
            />
            <BenefitBasesFields
                errors={errors}
                choices={choices}
                benefit={benefit}
            />
        </>
    );
}
