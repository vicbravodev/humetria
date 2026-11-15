import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';

/**
 * Closes the survey now: the links stop working, and the results exist only once it is closed. It cannot be reopened.
 * It opens from «Más» in the header, never as a button beside the reminder.
 */
export default function CloseDialog({
    surveyId,
    open,
    onOpenChange,
}: {
    surveyId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="¿Cerrar la encuesta?"
            description="Los enlaces dejan de funcionar y no se puede volver a abrir. Los resultados se muestran hasta que cierras, para que nadie quede señalado mientras se contesta."
            confirmLabel="Cerrar encuesta"
            form={routes.close.form([tenantSlug, surveyId])}
        />
    );
}
