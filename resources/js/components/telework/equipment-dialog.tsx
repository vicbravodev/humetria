import { Plus } from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/humetria/form-sheet';
import EquipmentFields from '@/components/telework/equipment-fields';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/telework/teleworkers/equipment';

/** Registers an item delivered to the person; they confirm having received it from their portal. */
export default function EquipmentDialog({
    teleworkerId,
    today,
}: {
    teleworkerId: string;
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title="Registrar equipo entregado"
            description="La persona lo confirma desde su portal, o lo rechaza explicando por qué."
            form={store.form({ tenant: tenantSlug, teleworker: teleworkerId })}
            submit="Registrar equipo"
            trigger={
                <SheetTrigger asChild>
                    <Button variant="outline">
                        <Plus aria-hidden />
                        Registrar equipo
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => <EquipmentFields today={today} errors={errors} />}
        </FormSheet>
    );
}
