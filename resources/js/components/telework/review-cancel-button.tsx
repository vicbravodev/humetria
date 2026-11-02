import { Form } from '@inertiajs/react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cancel } from '@/routes/tenant/telework/visit-authorizations';

/** Cancels a visit request nobody used; it is marked, not deleted. */
export default function ReviewCancelButton({
    authorizationId,
}: {
    authorizationId: string;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <Form
            {...cancel.form({
                tenant: tenantSlug,
                authorization: authorizationId,
            })}
        >
            {({ submit }) => (
                <ConfirmDialog
                    trigger={
                        <Button type="button" size="sm" variant="ghost">
                            Cancelar solicitud
                        </Button>
                    }
                    title="¿Cancelar la solicitud de visita?"
                    description="La persona ya no la verá en su portal. Podrás pedir otra autorización."
                    confirmLabel="Cancelar solicitud"
                    dismissLabel="Volver"
                    onConfirm={submit}
                />
            )}
        </Form>
    );
}
