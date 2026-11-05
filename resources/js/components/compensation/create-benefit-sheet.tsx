import { Plus } from 'lucide-react';
import { useState } from 'react';
import BenefitFormFields from '@/components/compensation/benefit-form-fields';
import CompensationSheet from '@/components/compensation/compensation-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/compensation/benefits';
import type { BenefitChoices } from '@/types';

/** «Nueva prestación»: one the contract has that is not in the base catalog, defined by its attributes. */
export default function CreateBenefitSheet({
    choices,
}: {
    choices: BenefitChoices;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <CompensationSheet
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Nueva prestación',
                description:
                    'Define cómo se calcula, a quién aplica y en qué bases cuenta. No hace falta más.',
                submit: 'Crear prestación',
            }}
            form={store.form(tenantSlug)}
            trigger={
                <SheetTrigger asChild>
                    <Button>
                        <Plus />
                        Nueva prestación
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <BenefitFormFields errors={errors} choices={choices} />
            )}
        </CompensationSheet>
    );
}
