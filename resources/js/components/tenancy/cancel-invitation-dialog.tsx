import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cancel } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Asks for confirmation before cancelling the invitation, so its link stops working. */
export default function CancelInvitationDialog({
    tenant,
    invitationId,
}: {
    tenant: TenantRow;
    invitationId: string;
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground"
                    aria-label={`Cancelar invitación de ${tenant.trade_name}`}
                >
                    Cancelar
                </Button>
            }
            title="¿Cancelar la invitación?"
            description="El enlace dejará de funcionar."
            confirmLabel="Cancelar invitación"
            dismissLabel="Volver"
            form={cancel.form({ tenant: tenant.id, invitation: invitationId })}
        />
    );
}
