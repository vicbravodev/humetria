import { useState } from 'react';
import WorkCenterDialog from '@/components/tenancy/work-center-dialog';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { store } from '@/routes/tenant/work-centers';
import type { MexicanStateOption } from '@/types';

export default function CreateWorkCenterDialog({
    tenantSlug,
    states,
}: {
    tenantSlug: string;
    states: MexicanStateOption[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <WorkCenterDialog
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Dar de alta centro de trabajo',
                description:
                    'Sus datos aparecerán en el informe de la NOM-035 de este centro.',
                submit: 'Dar de alta',
            }}
            form={store.form(tenantSlug)}
            states={states}
            trigger={
                <DialogTrigger asChild>
                    <Button>Dar de alta centro</Button>
                </DialogTrigger>
            }
        />
    );
}
