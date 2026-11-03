import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { close } from '@/routes/tenant/seating/survey';

/** Closes the survey before its last day, from its own button: the links stop working and the results open. It cannot be reopened. */
export default function SurveyCloseDialog({
    workCenterId,
}: {
    workCenterId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline">Cerrar la encuesta ahora</Button>
            }
            title="¿Cerrar la encuesta?"
            description="Los enlaces dejan de funcionar, ya no podrá contestar nadie más y no se puede volver a abrir. Al cerrar verás los resultados."
            confirmLabel="Cerrar encuesta"
            form={close.form([tenantSlug, workCenterId])}
        />
    );
}
