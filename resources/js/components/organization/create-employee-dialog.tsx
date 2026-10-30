import { useState } from 'react';
import EmployeeDialog from '@/components/organization/employee-dialog';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { store } from '@/routes/tenant/employees';
import type { EmployeeChoices } from '@/types';

export default function CreateEmployeeDialog({
    tenantSlug,
    choices,
}: {
    tenantSlug: string;
    choices: EmployeeChoices;
}) {
    const [open, setOpen] = useState(false);

    return (
        <EmployeeDialog
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Dar de alta empleado',
                description:
                    'Sus respuestas a las encuestas serán anónimas: RH solo verá resultados de grupos.',
                submit: 'Dar de alta',
            }}
            form={store.form(tenantSlug)}
            choices={choices}
            trigger={
                <DialogTrigger asChild>
                    <Button>Dar de alta empleado</Button>
                </DialogTrigger>
            }
        />
    );
}
