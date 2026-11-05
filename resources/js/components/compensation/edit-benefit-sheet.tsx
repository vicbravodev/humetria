import BenefitFormFields from '@/components/compensation/benefit-form-fields';
import CompensationSheet from '@/components/compensation/compensation-sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/compensation/benefits';
import type { BenefitChoices, BenefitRow } from '@/types';

/** Corrects the attributes of a benefit the company made up; its terms are edited apart. */
export default function EditBenefitSheet({
    benefit,
    choices,
    open,
    onOpenChange,
}: {
    benefit: BenefitRow;
    choices: BenefitChoices;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CompensationSheet
            open={open}
            onOpenChange={onOpenChange}
            copy={{
                title: 'Editar prestación',
                description: `Cambia cómo se define ${benefit.name}. Sus términos se quedan como están.`,
                submit: 'Guardar',
            }}
            form={update.form({ tenant: tenantSlug, benefit: benefit.id })}
        >
            {(errors) => (
                <BenefitFormFields
                    errors={errors}
                    choices={choices}
                    benefit={benefit}
                />
            )}
        </CompensationSheet>
    );
}
