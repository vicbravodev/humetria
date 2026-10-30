import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ReportField from '@/components/results/report-field';
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
import { update } from '@/routes/tenant/campaigns/report';
import type { ReportForm } from '@/types';

/** Where HR writes the parts of the report the platform cannot: objective, activities, conclusions and the rest. */
export default function ReportDetailsDialog({
    tenantSlug,
    campaignId,
    form,
    complete,
}: {
    tenantSlug: string;
    campaignId: string;
    form: ReportForm;
    /** Whether HR already filled it in: then it is edited, not completed. */
    complete: boolean;
}) {
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
                    {({ processing, errors }) => (
                        <>
                            <ReportField
                                name="objective"
                                label="Objetivo"
                                value={form.objective}
                                error={errors['objective']}
                                multiline
                            />
                            <ReportField
                                name="activities"
                                label="Principales actividades realizadas en el centro"
                                value={form.activities}
                                error={errors['activities']}
                                multiline
                            />
                            <ReportField
                                name="conclusions"
                                label="Conclusiones"
                                value={form.conclusions}
                                error={errors['conclusions']}
                                multiline
                            />
                            <ReportField
                                name="recommendations"
                                label="Recomendaciones y acciones de intervención"
                                value={form.recommendations}
                                error={errors['recommendations']}
                                multiline
                            />
                            <ReportField
                                name="responsible_name"
                                label="Responsable de la evaluación"
                                value={form.responsible_name}
                                error={errors['responsible_name']}
                                hint="Nombre completo."
                            />
                            <ReportField
                                name="responsible_license"
                                label="Cédula profesional (opcional)"
                                value={form.responsible_license}
                                error={errors['responsible_license']}
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
