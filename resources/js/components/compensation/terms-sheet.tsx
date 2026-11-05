import CompensationSheet from '@/components/compensation/compensation-sheet';
import TermFormFields from '@/components/compensation/term-form-fields';
import TermHistory from '@/components/compensation/term-history';
import FormSection from '@/components/compensation/form-section';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/compensation/benefits/terms';
import type { BenefitRow } from '@/types';

/** The terms of a benefit by vigencia: what they have been, and a form for the next ones. */
export default function TermsSheet({
    benefit,
    open,
    onOpenChange,
}: {
    benefit: BenefitRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CompensationSheet
            open={open}
            onOpenChange={onOpenChange}
            copy={{
                title: `Términos de ${benefit.name}`,
                description: `Se calcula en ${benefit.unit}. Cada vigencia reemplaza a la anterior desde su fecha.`,
                submit: 'Guardar términos',
            }}
            form={store.form({ tenant: tenantSlug, benefit: benefit.id })}
            above={
                <FormSection title="Vigentes y anteriores">
                    <TermHistory terms={benefit.terms} />
                </FormSection>
            }
        >
            {(errors) => <TermFormFields errors={errors} benefit={benefit} />}
        </CompensationSheet>
    );
}
