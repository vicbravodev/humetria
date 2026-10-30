import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import EmployeeSheet from '@/components/organization/employee-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { store } from '@/routes/tenant/employees';
import type { EmployeeChoices } from '@/types';

export default function CreateEmployeeSheet({
    tenantSlug,
    choices,
}: {
    tenantSlug: string;
    choices: EmployeeChoices;
}) {
    const [open, setOpen] = useState(false);

    return (
        <EmployeeSheet
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Dar de alta',
                description:
                    'Una persona de tu plantilla, para incluirla en las evaluaciones.',
                submit: 'Dar de alta',
            }}
            form={store.form(tenantSlug)}
            choices={choices}
            trigger={
                <SheetTrigger asChild>
                    <Button>
                        <UserPlus />
                        Dar de alta
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
