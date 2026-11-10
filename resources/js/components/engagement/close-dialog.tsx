import { Lock } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { close } from '@/routes/tenant/climate';

/** Closes the survey now: the links stop working, and the results exist only once it is closed. It cannot be reopened. */
export default function CloseDialog({ surveyId }: { surveyId: string }) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline">
                    <Lock aria-hidden="true" />
                    Cerrar encuesta
                </Button>
            }
            title="¿Cerrar la encuesta?"
            description="Los enlaces dejan de funcionar y no se puede volver a abrir. Los resultados se muestran hasta que cierras, para que nadie quede señalado mientras se contesta."
            confirmLabel="Cerrar encuesta"
            form={close.form([tenantSlug, surveyId])}
        />
    );
}
