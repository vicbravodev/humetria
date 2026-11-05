import CheckField from '@/components/compensation/check-field';
import BenefitSbcField from '@/components/compensation/benefit-sbc-field';
import FormSection from '@/components/compensation/form-section';
import FormField from '@/components/form-field';
import type { BenefitChoices, BenefitRow } from '@/types';

/** What a benefit weighs on: the salario base de cotización (always, never or up to a cap), the payroll tax, the PTU. */
export default function BenefitBasesFields({
    errors,
    choices,
    benefit,
}: {
    errors: Partial<Record<string, string>>;
    choices: BenefitChoices;
    benefit: BenefitRow | undefined;
}) {
    return (
        <FormSection title="En qué bases cuenta">
            <BenefitSbcField
                errors={errors}
                choices={choices}
                benefit={benefit}
            />
            <div className="grid gap-3">
                <CheckField
                    name="in_payroll_tax"
                    label="Entra a la base del impuesto sobre nómina"
                    defaultChecked={benefit?.in_payroll_tax ?? true}
                />
                <CheckField
                    name="in_ptu_base"
                    label="Entra a la base del reparto de utilidades (PTU)"
                    defaultChecked={benefit?.in_ptu_base ?? false}
                />
                <CheckField
                    name="in_seniority_base"
                    label="Cuenta para la base de la prima de antigüedad"
                    defaultChecked={benefit?.in_seniority_base ?? false}
                />
                <CheckField
                    name="income_tax_exempt"
                    label="Está exenta de ISR (solo se señala, no se calcula)"
                    defaultChecked={benefit?.income_tax_exempt ?? false}
                />
            </div>
            <FormField
                name="cap"
                idPrefix="benefit"
                label="Tope por persona (pesos, opcional)"
                inputMode="decimal"
                autoComplete="off"
                defaultValue={benefit?.cap}
                error={errors['cap']}
            />
        </FormSection>
    );
}
