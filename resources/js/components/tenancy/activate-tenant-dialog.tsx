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
import { activationCopy } from '@/lib/tenant-status';
import { activate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/**
 * Activates a company, reactivates it when it is blocked, or moves the end date of an active one, until the day the person picks.
 * `minDate` (tomorrow in Mexico City time) comes from the server, so the browser does no timezone math.
 */
export default function ActivateTenantDialog({
    tenant,
    minDate,
}: {
    tenant: TenantRow;
    minDate: string;
}) {
    const [open, setOpen] = useState(false);
    const copy = activationCopy(tenant.status);
    const current =
        tenant.active_until !== null && tenant.active_until >= minDate
            ? tenant.active_until
            : undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`${copy.action} ${tenant.trade_name}`}
                >
                    {copy.action}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{copy.title}</DialogTitle>
                <DialogDescription>
                    Elige el último día que {tenant.trade_name} tendrá acceso.
                </DialogDescription>
                <Form
                    {...activate.form(tenant.id)}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-active_until">
                                    Activa hasta
                                </Label>
                                <DatePicker
                                    id="tenant-active_until"
                                    name="active_until"
                                    min={minDate}
                                    defaultValue={current}
                                    invalid={
                                        errors['active_until'] !== undefined
                                    }
                                    describedBy="tenant-active_until-error"
                                />
                                <InputError
                                    id="tenant-active_until-error"
                                    role="alert"
                                    message={errors['active_until']}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {copy.submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
