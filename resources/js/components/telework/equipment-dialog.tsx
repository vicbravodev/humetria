import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import EquipmentFields from '@/components/telework/equipment-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/teleworkers/equipment';

/** Registers an item delivered to the person; they confirm having received it from their portal. */
export default function EquipmentDialog({
    tenantSlug,
    teleworkerId,
    today,
}: {
    tenantSlug: string;
    teleworkerId: string;
    today: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus aria-hidden />
                    Registrar equipo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Registrar equipo entregado</DialogTitle>
                <DialogDescription>
                    La persona lo confirma desde su portal, o lo rechaza
                    explicando por qué.
                </DialogDescription>
                <Form
                    {...store.form({
                        tenant: tenantSlug,
                        teleworker: teleworkerId,
                    })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <EquipmentFields today={today} errors={errors} />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Registrar equipo
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
