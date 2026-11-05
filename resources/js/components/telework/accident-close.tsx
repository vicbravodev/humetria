import { Form } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import AccidentStep from '@/components/telework/accident-step';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { close } from '@/routes/tenant/telework/notices';
import type { AccidentNotice } from '@/types';

const TEXT =
    'Agrega una evidencia (constancia, foto del cambio, acuerdo firmado) y cierra el aviso. Queda en tu expediente.';

/** Step 3 of an accident (board V3-Aviso): its evidence and the closing, open once what was done is recorded. */
export default function AccidentClose({ notice }: { notice: AccidentNotice }) {
    const tenantSlug = useTenantSlug();

    if (notice.closedOn !== null) {
        return (
            <AccidentStep
                number={3}
                state="done"
                title="Cierra cuando esté resuelto"
                text={`Cerrado el ${describeDate(notice.closedOn) ?? notice.closedOn}.`}
            >
                {notice.evidenceHref !== null && (
                    <a
                        href={notice.evidenceHref}
                        className="touch-hit inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                    >
                        <FileCheck aria-hidden className="size-4" />
                        Ver la evidencia
                    </a>
                )}
            </AccidentStep>
        );
    }

    const ready = notice.reviewedOn !== null;

    return (
        <AccidentStep
            number={3}
            state={ready ? 'now' : 'next'}
            title="Cierra cuando esté resuelto"
            text={TEXT}
        >
            {ready ? (
                <Form
                    {...close.form({ tenant: tenantSlug, notice: notice.id })}
                    options={{ preserveScroll: true }}
                    className="grid gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <FileDropZone
                                name="evidence"
                                accept="application/pdf,image/jpeg,image/png"
                                title="Arrastra la evidencia aquí o elígela"
                                hint="PDF, JPG o PNG"
                                invalid={errors['evidence'] !== undefined}
                                describedBy="accident-evidence-error"
                            />
                            <InputError
                                id="accident-evidence-error"
                                message={errors['evidence'] ?? errors['notice']}
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                {processing && <Spinner />}
                                Cerrar aviso
                            </Button>
                        </>
                    )}
                </Form>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Se abre al registrar qué se hizo.
                </p>
            )}
        </AccidentStep>
    );
}
