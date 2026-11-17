import { Plus } from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/humetria/form-sheet';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/telework/teleworkers/workplaces';

/** One more place where the person teleworks; its first checklist is requested at once. */
export default function AddWorkplaceDialog({
    teleworkerId,
    states,
}: {
    teleworkerId: string;
    states: Record<string, string>;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title="Agregar un lugar de trabajo"
            description="Se le pedirá la lista de verificación de este lugar."
            form={store.form({ tenant: tenantSlug, teleworker: teleworkerId })}
            submit="Agregar lugar"
            trigger={
                <SheetTrigger asChild>
                    <Button variant="outline">
                        <Plus aria-hidden />
                        Agregar lugar
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <WorkplaceFields index={null} states={states} errors={errors} />
            )}
        </FormSheet>
    );
}
