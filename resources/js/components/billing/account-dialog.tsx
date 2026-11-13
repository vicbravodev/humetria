import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AccountFormFields from '@/components/billing/account-form-fields';
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
import { store, update } from '@/routes/billing/accounts';
import type { AccountDetail, SatOption } from '@/types';

/**
 * Creates a payer account, or, given one, corrects its data. Creating opens from its own button; editing is opened by the
 * page that holds the account, which controls `open`.
 */
export default function AccountDialog({
    account,
    taxRegimes,
    cfdiUses,
}: {
    account?: AccountDetail;
    taxRegimes: SatOption[];
    cfdiUses: SatOption[];
}) {
    const [open, setOpen] = useState(false);
    const editing = account !== undefined;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {editing ? (
                    <Button variant="outline">Editar cuenta</Button>
                ) : (
                    <Button>
                        <Plus aria-hidden />
                        Crear cuenta
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogTitle>
                    {editing ? 'Editar cuenta' : 'Crear cuenta'}
                </DialogTitle>
                <DialogDescription>
                    {editing
                        ? `Corrige los datos de ${account.legal_name}.`
                        : 'Quien paga por una o varias empresas. Después se le vinculan sus empresas y se les asigna un plan.'}
                </DialogDescription>
                <Form
                    {...(editing ? update.form(account.id) : store.form())}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <AccountFormFields
                                errors={errors}
                                taxRegimes={taxRegimes}
                                cfdiUses={cfdiUses}
                                {...(editing ? { account } : {})}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {editing ? 'Guardar' : 'Guardar cuenta'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
