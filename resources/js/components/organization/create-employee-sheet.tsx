import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import EmployeeSheet from '@/components/organization/employee-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useIsUnion } from '@/hooks/use-is-union';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/employees';
import type { EmployeeChoices } from '@/types';

export default function CreateEmployeeSheet({
    choices,
    full,
}: {
    choices: EmployeeChoices;
    /** The plan has no room for another person: the button is off, and the notice above says what to do. */
    full: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const union = useIsUnion();
    const [open, setOpen] = useState(false);

    return (
        <EmployeeSheet
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Dar de alta',
                description: union
                    ? 'Una persona del padrón de la empresa. Basta con su ID.'
                    : 'Una persona de tu plantilla, para incluirla en las evaluaciones.',
                submit: 'Dar de alta',
            }}
            form={store.form(tenantSlug)}
            choices={choices}
            trigger={
                <SheetTrigger asChild>
                    <Button
                        data-tour="add-employee"
                        disabled={full}
                        title={
                            full
                                ? 'Tu plan ya cubre a todas las personas que incluye: pide una ampliación.'
                                : undefined
                        }
                    >
                        <UserPlus />
                        Dar de alta
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
