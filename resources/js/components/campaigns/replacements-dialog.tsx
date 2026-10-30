import { Form } from '@inertiajs/react';
import { useState } from 'react';
import FormField from '@/components/form-field';
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
import { replacements } from '@/routes/tenant/campaigns';

/** Draws more people into the sample when some of the drawn will not answer (III.2 c). */
export default function ReplacementsDialog({
    tenantSlug,
    campaignId,
    suggested,
}: {
    tenantSlug: string;
    campaignId: string;
    suggested: number;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Sortear reemplazos</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Sortear reemplazos para la muestra</DialogTitle>
                <DialogDescription>
                    Se eligen al azar entre quienes no están en la muestra, en
                    proporción por género, y reciben la Guía III por correo.
                </DialogDescription>
                <Form
                    {...replacements.form([tenantSlug, campaignId])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                name="count"
                                label="¿Cuántas personas más?"
                                idPrefix="replacements"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                defaultValue={String(Math.max(suggested, 1))}
                                error={errors['count']}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Sortear e invitar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
