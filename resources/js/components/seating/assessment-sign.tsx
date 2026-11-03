import ConfirmDialog from '@/components/humetria/confirm-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { sign } from '@/routes/tenant/seating/assessments';

/** The signature of the Comisión: one-way, so it asks first. What the platform still misses shows under the button. */
export default function AssessmentSign({
    assessmentId,
    error,
}: {
    assessmentId: string;
    error: string | undefined;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section aria-label="Firma" className="flex flex-col items-end gap-2">
            <ConfirmDialog
                trigger={<Button>Firmar evaluación</Button>}
                form={sign.form([tenantSlug, assessmentId])}
                title="¿Firmar esta evaluación?"
                description="Al firmar, la plataforma calcula el total y el nivel de riesgo, y la evaluación queda fija: no se edita. Si algo cambia después, se evalúa de nuevo y esta queda en el historial."
                confirmLabel="Firmar"
                destructive={false}
            />
            <InputError message={error} />
        </section>
    );
}
