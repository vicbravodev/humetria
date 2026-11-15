import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ReportFields from '@/components/results/report-details-fields';
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
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/campaigns/report';
import type { ReportForm } from '@/types';

/** Where HR writes the parts of the report the platform cannot: objective, activities, conclusions and the rest. */
export default function ReportDetailsDialog({
    campaignId,
    form,
    complete,
}: {
    campaignId: string;
    form: ReportForm;
    /** Whether HR already filled it in: then it is edited, not completed. */
    complete: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    {complete ? 'Editar informe' : 'Completar informe'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Completar el informe</DialogTitle>
                <DialogDescription>
                    Lo que pide el numeral 7.7 y la plataforma no puede saber.
                    Los datos del centro, el método y los resultados se llenan
                    solos.
                </DialogDescription>
                <Form
                    {...update.form([tenantSlug, campaignId])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors, clearErrors }) => (
                        <>
                            <ReportFields
                                form={form}
                                errors={processing ? {} : errors}
                                clearErrors={clearErrors}
                            />
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
