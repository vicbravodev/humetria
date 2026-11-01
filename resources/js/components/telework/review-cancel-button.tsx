import { Form } from '@inertiajs/react';
import ConfirmAction from '@/components/telework/confirm-action';
import { Button } from '@/components/ui/button';
import { cancel } from '@/routes/tenant/telework/visit-authorizations';

/** Cancels a visit request nobody used; it is marked, not deleted. */
export default function ReviewCancelButton({
    tenantSlug,
    authorizationId,
}: {
    tenantSlug: string;
    authorizationId: string;
}) {
    return (
        <Form
            {...cancel.form({
                tenant: tenantSlug,
                authorization: authorizationId,
            })}
        >
            {({ submit }) => (
                <ConfirmAction
                    title="¿Cancelar la solicitud de visita?"
                    description="La persona ya no la verá en su portal. Podrás pedir otra autorización."
                    confirmLabel="Cancelar solicitud"
                    destructive
                    onConfirm={submit}
                >
                    <Button type="button" size="sm" variant="ghost">
                        Cancelar solicitud
                    </Button>
                </ConfirmAction>
            )}
        </Form>
    );
}
