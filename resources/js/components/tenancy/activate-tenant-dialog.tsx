import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TenantField from '@/components/tenancy/tenant-field';
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
import { activate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/**
 * Activates a company, or reactivates it when it is blocked, until the day the person picks.
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
    const verb = tenant.blocked ? 'Reactivar' : 'Activar';
    const current =
        tenant.active_until !== null && tenant.active_until >= minDate
            ? tenant.active_until
            : undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`${verb} ${tenant.trade_name}`}
                >
                    {verb}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{verb} empresa</DialogTitle>
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
                            <TenantField
                                type="date"
                                name="active_until"
                                label="Activa hasta"
                                required
                                min={minDate}
                                defaultValue={current}
                                error={errors['active_until']}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {verb}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
