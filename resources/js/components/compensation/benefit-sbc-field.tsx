import { useState } from 'react';
import ChoiceField from '@/components/compensation/choice-field';
import FormField from '@/components/form-field';
import type { BenefitChoices, BenefitRow } from '@/types';

/** Whether the benefit integrates the salario base de cotización (always, never or up to a cap) and, if up to a cap, which. */
export default function BenefitSbcField({
    errors,
    choices,
    benefit,
}: {
    errors: Partial<Record<string, string>>;
    choices: BenefitChoices;
    benefit: BenefitRow | undefined;
}) {
    const [integration, setIntegration] = useState(
        benefit?.sbc_integration ?? 'always',
    );

    return (
        <div className="grid items-start gap-4 sm:grid-cols-2">
            <ChoiceField
                name="sbc_integration"
                label="Integra al salario base de cotización"
                source={{ options: choices.sbcIntegrations }}
                defaultValue={choices.sbcIntegrations.find(
                    (option) => option.value === integration,
                )}
                error={errors['sbc_integration']}
                onChange={(option) => setIntegration(option.value)}
            />
            {integration === 'up_to_cap' && (
                <FormField
                    name="sbc_cap"
                    idPrefix="benefit"
                    label="Integra hasta (pesos)"
                    inputMode="decimal"
                    autoComplete="off"
                    defaultValue={benefit?.sbc_cap}
                    error={errors['sbc_cap']}
                />
            )}
        </div>
    );
}
