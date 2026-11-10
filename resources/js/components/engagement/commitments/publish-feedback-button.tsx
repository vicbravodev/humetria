import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { publish } from '@/routes/tenant/climate';

/** Publishes the commitments to the portal of the people of the company, once, and tells them by email. */
export default function PublishFeedbackButton({
    surveyId,
}: {
    surveyId: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button>
                    <Send aria-hidden />
                    Publicar en el portal
                </Button>
            }
            title="Publicar lo que haremos"
            description="Todas las personas activas recibirán un correo con su enlace y verán en su portal los compromisos de la empresa y de sus propios grupos. Los resultados de cada grupo no se publican."
            confirmLabel="Publicar"
            destructive={false}
            form={publish.form([tenantSlug, surveyId])}
        />
    );
}
