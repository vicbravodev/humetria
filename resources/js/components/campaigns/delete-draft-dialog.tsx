import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/tenant/campaigns';

export default function DeleteDraftDialog({
    tenantSlug,
    campaignId,
}: {
    tenantSlug: string;
    campaignId: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost">Eliminar borrador</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Eliminar este borrador?</DialogTitle>
                <DialogDescription>
                    Nadie fue invitado todavía. Podrás preparar otra evaluación
                    de este centro cuando quieras.
                </DialogDescription>
                <Form {...destroy.form([tenantSlug, campaignId])}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Eliminar borrador
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
