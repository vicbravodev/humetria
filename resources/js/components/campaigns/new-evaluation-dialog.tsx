import { Form } from '@inertiajs/react';
import { useState } from 'react';
import PopulationField from '@/components/campaigns/population-field';
import WorkCenterSelect from '@/components/campaigns/work-center-select';
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
    workCenters,
}: {
    tenantSlug: string;
    workCenters: WorkCenterEvaluation[];
}) {
    const [open, setOpen] = useState(false);
    const [workCenterId, setWorkCenterId] = useState('');
    const eligible = workCenters.filter((workCenter) => workCenter.eligible);
    const chosen = eligible.find(
        (workCenter) => workCenter.id === workCenterId,
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={eligible.length === 0}>
                    Nueva evaluación
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogTitle>Nueva evaluación NOM-035</DialogTitle>
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
                            <WorkCenterSelect
                                workCenters={eligible}
                                value={workCenterId}
                                onChange={setWorkCenterId}
                                error={errors['work_center_id']}
                            />
                            {chosen?.guides.includes('III') === true && (
                                <PopulationField
                                    workers={chosen.workers}
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
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || chosen === undefined
                                    }
                                >
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
