import { Form } from '@inertiajs/react';
import TenantFormFields from '@/components/tenancy/tenant-form-fields';
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
import { update } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** Corrects a company's data; opened from its row's menu. */
export default function EditTenantDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Editar empresa</DialogTitle>
                <DialogDescription>
                    Corrige los datos de {tenant.trade_name}.
                </DialogDescription>
                <Form
                    {...update.form(tenant.id)}
                    noValidate
                    onSuccess={() => onOpenChange(false)}
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
