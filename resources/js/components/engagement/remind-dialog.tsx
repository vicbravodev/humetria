import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { remind } from '@/routes/tenant/climate';

/** Sends the same link again to whoever has not answered. Nobody is told who the others are: the survey does not know. */
export default function RemindDialog({
    surveyId,
    pending,
}: {
    surveyId: string;
    pending: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button>
                    <Send aria-hidden="true" />
                    Recordar a {pending}
                </Button>
            }
            title="¿Enviar el recordatorio?"
            description="Le llega otra vez su enlace, el mismo, a quien todavía no contesta. Quien ya contestó no recibe nada."
            confirmLabel="Enviar recordatorio"
            destructive={false}
            form={remind.form([tenantSlug, surveyId])}
            preserveScroll
        />
    );
}
