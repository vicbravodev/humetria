import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
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
import { store } from '@/routes/companies';
import type { TenantPrefill } from '@/types';

/** Dar de alta una empresa; when it comes from a demo request, it opens already filled and names who asked. */
export default function CreateTenantDialog({
    prefill,
}: {
    prefill: TenantPrefill | null;
}) {
    const [open, setOpen] = useState(prefill !== null);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden />
                    Dar de alta empresa
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Dar de alta empresa</DialogTitle>
                <DialogDescription>
                    La empresa queda sin iniciar: su prueba de 14 días empieza
                    cuando su responsable entre por primera vez.
                    {prefill !== null &&
                        ` Viene de la solicitud de ${prefill.contact} (${prefill.email}).`}
                </DialogDescription>
                <Form
                    {...store.form()}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {prefill !== null && (
                                <input
                                    type="hidden"
                                    name="demo_request_id"
                                    value={prefill.demo_request_id}
                                />
                            )}
                            <InputError message={errors['demo_request_id']} />
                            <TenantFormFields
                                errors={errors}
                                prefill={prefill}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Dar de alta
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
