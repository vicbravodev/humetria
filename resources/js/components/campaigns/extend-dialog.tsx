import { Form } from '@inertiajs/react';
import { useState } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { extend } from '@/routes/tenant/campaigns';

/** Gives people more days to answer; the last day only moves later. */
export default function ExtendDialog({
    tenantSlug,
    campaignId,
    closesOn,
    min,
}: {
    tenantSlug: string;
    campaignId: string;
    closesOn: string | null;
    min: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Extender fecha</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Extender la fecha de cierre</DialogTitle>
                <DialogDescription>
                    Hoy cierra el {closesOn}. Las personas pueden contestar
                    hasta el final del nuevo día.
                </DialogDescription>
                <Form
                    {...extend.form([tenantSlug, campaignId])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="extend-closes_on">
                                    Nuevo último día
                                </Label>
                                <DatePicker
                                    id="extend-closes_on"
                                    name="closes_on"
                                    min={min}
                                    invalid={errors['closes_on'] !== undefined}
                                    describedBy="extend-closes_on-error"
                                />
                                <InputError
                                    id="extend-closes_on-error"
                                    role="alert"
                                    message={errors['closes_on']}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Extender
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
