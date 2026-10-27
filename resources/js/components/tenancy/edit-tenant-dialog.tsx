import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TenantFormFields from '@/components/tenancy/tenant-form-fields';
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
import { update } from '@/routes/companies';
import type { TenantRow } from '@/types';

export default function EditTenantDialog({ tenant }: { tenant: TenantRow }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`Editar ${tenant.trade_name}`}
                >
                    Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Editar empresa</DialogTitle>
                <DialogDescription>
                    Corrige los datos de {tenant.trade_name}.
                </DialogDescription>
                <Form
                    {...update.form(tenant.id)}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TenantFormFields errors={errors} tenant={tenant} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
