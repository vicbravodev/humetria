import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import WorkplaceFields from '@/components/telework/workplace-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/teleworkers/workplaces';

/** One more place where the person teleworks; its first checklist is requested at once. */
export default function AddWorkplaceDialog({
    tenantSlug,
    teleworkerId,
    states,
}: {
    tenantSlug: string;
    teleworkerId: string;
    states: Record<string, string>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus aria-hidden />
                    Agregar lugar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Agregar un lugar de trabajo</DialogTitle>
                <DialogDescription>
                    Se le pedirá la lista de verificación de este lugar.
                </DialogDescription>
                <Form
                    {...store.form({
                        tenant: tenantSlug,
                        teleworker: teleworkerId,
                    })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <WorkplaceFields
                                index={null}
                                states={states}
                                errors={errors}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Agregar lugar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
