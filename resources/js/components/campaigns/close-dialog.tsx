import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { close } from '@/routes/tenant/campaigns';

/**
 * Closes the evaluation now, opened from the page's menu. With the sample short it says so first: the norm asks that the answers reach it
 * (III.2 c), and closing is final.
 */
export default function CloseDialog({
    tenantSlug,
    campaignId,
    shortBy,
    open,
    onOpenChange,
}: {
    tenantSlug: string;
    campaignId: string;
    /** Answers missing to reach the sample; 0 when it is met or there is none. */
    shortBy: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>¿Cerrar la evaluación?</DialogTitle>
                <DialogDescription>
                    Los enlaces dejan de funcionar y no se puede volver a abrir.
                </DialogDescription>
                {shortBy > 0 && (
                    <p className="rounded-lg border border-destructive/40 p-3 text-sm text-destructive">
                        Faltan {shortBy} respuestas para el mínimo de la muestra
                        que pide la norma. Considera extender la fecha o sortear
                        reemplazos antes de cerrar.
                    </p>
                )}
                <Form {...close.form([tenantSlug, campaignId])}>
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
                                Cerrar evaluación
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
