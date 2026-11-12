import { Form, usePage } from '@inertiajs/react';
import CancelInvitationDialog from '@/components/tenancy/cancel-invitation-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { resend } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Sends the owner's pending invitation again (support too, it changes nothing), or cancels it (the superadmin's: it changes who can enter). */
export default function TenantInvitationActions({
    tenant,
    invitationId,
}: {
    tenant: TenantRow;
    invitationId: string;
}) {
    const { auth } = usePage().props;

    return (
        <div className="-ml-3 flex">
            <Form
                {...resend.form({
                    tenant: tenant.id,
                    invitation: invitationId,
                })}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        disabled={processing}
                        aria-label={`Reenviar invitación de ${tenant.trade_name}`}
                    >
                        {processing && <Spinner />}
                        Reenviar
                    </Button>
                )}
            </Form>
            {auth.isSuperadmin && (
                <CancelInvitationDialog
                    tenant={tenant}
                    invitationId={invitationId}
                />
            )}
        </div>
    );
}
