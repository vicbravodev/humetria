import { useState } from 'react';
import OptionCards from '@/components/compensation/option-cards';
import TenureTableField from '@/components/compensation/tenure-table-field';
import FormField from '@/components/form-field';
import PillChoice from '@/components/humetria/pill-choice';
import { SBC_HINTS } from '@/lib/compensation/benefit-draft';
import type { ChoiceOption } from '@/types';

const GROUPS: { value: '' | 'unionized' | 'trusted'; label: string }[] = [
    { value: '', label: 'Todos' },
    { value: 'unionized', label: 'Solo sindicalizados' },
    { value: 'trusted', label: 'Solo confianza' },
];

/** «¿A quién le toca?», «¿Cambia con la antigüedad?» with its table and «¿Cuenta para las cuotas del IMSS?». */
export default function BenefitReachFields({
    unit,
    sbcIntegrations,
    group,
    onGroup,
    errors,
}: {
    unit: string;
    sbcIntegrations: ChoiceOption[];
    group: string;
    onGroup: (group: string) => void;
    errors: Partial<Record<string, string>>;
}) {
    const [seniority, setSeniority] = useState<'no' | 'yes'>('no');
    const [sbc, setSbc] = useState('always');

    return (
        <>
            <PillChoice
                name="group"
                legend="¿A quién le toca?"
                options={GROUPS}
                value={
                    GROUPS.find((option) => option.value === group)?.value ?? ''
                }
                onChange={onGroup}
            />
            <PillChoice
                name="has_seniority"
                legend="¿Cambia con la antigüedad?"
                options={[
                    { value: 'no', label: 'No' },
                    { value: 'yes', label: 'Sí, por años' },
                ]}
                value={seniority}
                onChange={setSeniority}
            />
            <fieldset
                disabled={seniority === 'no'}
                hidden={seniority === 'no'}
                className="grid sm:max-w-[520px]"
            >
                <TenureTableField
                    initial={[{ from_years: '0', value: '' }]}
                    unit={unit}
                    error={errors['tenure_table']}
                />
            </fieldset>
            <div className="grid gap-2">
                <OptionCards
                    name="sbc_integration"
                    legend="¿Cuenta para las cuotas del IMSS?"
                    options={[...sbcIntegrations]
                        .sort(
                            (a, b) =>
                                Object.keys(SBC_HINTS).indexOf(a.value) -
                                Object.keys(SBC_HINTS).indexOf(b.value),
                        )
                        .map((option) => ({
                            ...option,
                            hint: SBC_HINTS[option.value],
                        }))}
                    value={sbc}
                    columns={1}
                    onChange={setSbc}
                />
                {sbc === 'up_to_cap' && (
                    <div className="sm:max-w-[260px]">
                        <FormField
                            name="sbc_cap"
                            idPrefix="benefit"
                            label="Integra hasta (pesos)"
                            inputMode="decimal"
                            autoComplete="off"
                            error={errors['sbc_cap']}
                        />
                    </div>
                )}
                <p className="text-sm text-muted-foreground">
                    Si no lo sabes, pregúntale a quien hace tu nómina. Puedes
                    cambiarlo después.
                </p>
            </div>
        </>
    );
}
