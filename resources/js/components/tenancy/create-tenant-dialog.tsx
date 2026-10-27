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
import { store } from '@/routes/companies';

export default function CreateTenantDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Dar de alta empresa</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Dar de alta empresa</DialogTitle>
                <DialogDescription>
                    La empresa queda sin iniciar: su prueba de 14 días empieza
                    cuando su responsable entre por primera vez.
                </DialogDescription>
                <Form
                    {...store.form()}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TenantFormFields errors={errors} />
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
