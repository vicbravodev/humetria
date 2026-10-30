import { Form } from '@inertiajs/react';
import { useState } from 'react';
import PopulationField from '@/components/campaigns/population-field';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { store } from '@/routes/tenant/campaigns';
import type { WorkCenterEvaluation } from '@/types';

/**
 * Starts a NOM-035 evaluation of one work center as a draft: its people decide the guides, and with Guía III RH
 * chooses everyone or a sample. Nothing is sent until the draft is launched.
 */
export default function NewEvaluationDialog({
    tenantSlug,
    workCenter,
}: {
    tenantSlug: string;
    workCenter: WorkCenterEvaluation;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Evaluar
                    <span className="sr-only"> {workCenter.name}</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogTitle>Evaluar {workCenter.name}</DialogTitle>
                <DialogDescription>
                    Se prepara como borrador. Nadie recibe nada hasta que la
                    lances.
                </DialogDescription>
                <Form
                    {...store.form(tenantSlug)}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="work_center_id"
                                value={workCenter.id}
                            />
                            <InputError message={errors['work_center_id']} />
                            {workCenter.guides.includes('III') && (
                                <PopulationField
                                    workers={workCenter.workers}
                                    error={errors['population']}
                                />
                            )}
                            <Alert>
                                <AlertDescription>
                                    La plantilla debe estar completa: la norma
                                    cuenta a todas las personas que trabajan en
                                    el centro, y con ese número se decide qué
                                    guías aplican.
                                </AlertDescription>
                            </Alert>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Preparar borrador
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
