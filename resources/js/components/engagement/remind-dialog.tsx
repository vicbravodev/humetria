import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';

/** Sends the same link again to whoever has not answered. Nobody is told who the others are: the survey does not know. */
export default function RemindDialog({
    surveyId,
    pending,
    outline = false,
}: {
    surveyId: string;
    pending: number;
    /** Beside another primary action (the home), the reminder is the secondary one. */
    outline?: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();

    return (
        <ConfirmDialog
            trigger={
                <Button variant={outline ? 'outline' : 'default'}>
                    <Send aria-hidden="true" />
                    Recordar a {pending}
                </Button>
            }
            title="¿Enviar el recordatorio?"
            description="Le llega otra vez su enlace, el mismo, a quien todavía no contesta. Quien ya contestó no recibe nada."
            confirmLabel="Enviar recordatorio"
            destructive={false}
            form={routes.remind.form([tenantSlug, surveyId])}
            preserveScroll
        />
    );
}
