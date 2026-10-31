import { Form } from '@inertiajs/react';
import FormField from '@/components/form-field';
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
import { replacements } from '@/routes/tenant/campaigns';
import type { ReplacementPool } from '@/types';
import ReplacementGenderField from '@/components/campaigns/replacement-gender-field';

/**
 * Draws more people into the sample when some of the drawn will not answer (III.2 c): of the same gender as them, so the
 * sample keeps its split (III.1).
 */
export default function ReplacementsDialog({
    tenantSlug,
    campaignId,
    pool,
    open,
    onOpenChange,
}: {
    tenantSlug: string;
    campaignId: string;
    pool: ReplacementPool[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Sortear reemplazos para la muestra</DialogTitle>
                <DialogDescription>
                    Se eligen al azar entre quienes no están en la muestra, del
                    mismo género de quienes no contestarán, y reciben la Guía
                    III por correo.
                </DialogDescription>
                <Form
                    {...replacements.form([tenantSlug, campaignId])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ReplacementGenderField
                                pool={pool}
                                error={errors['gender']}
                            />
                            <FormField
                                name="count"
                                label="¿Cuántas personas más?"
                                idPrefix="replacements"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                defaultValue="1"
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
