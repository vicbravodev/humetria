import { Form } from '@inertiajs/react';
import CancelInvitationDialog from '@/components/tenancy/cancel-invitation-dialog';
import TenantInvitationLines from '@/components/tenancy/tenant-invitation-lines';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { resend } from '@/routes/companies/invitations';
import type { TenantRow } from '@/types';

/** Who owns the company, and the invitation still waiting for an answer, if any. */
export default function TenantOwnerCell({ tenant }: { tenant: TenantRow }) {
    const { owner, invitation } = tenant;

    if (owner === null && invitation === null) {
        return <p className="text-sm text-muted-foreground">Sin invitar</p>;
    }

    return (
        <div className="grid gap-3">
            {owner !== null && (
                <div className="grid justify-items-start gap-1">
                    <Badge>Activo</Badge>
                    <p className="text-sm text-muted-foreground">
                        {owner.name} · {owner.email}
                    </p>
                </div>
            )}
            {invitation !== null && (
                <div className="grid justify-items-start gap-2">
                    <TenantInvitationLines invitation={invitation} />
                    <div className="flex gap-2">
                        <Form
                            {...resend.form({
                                tenant: tenant.id,
                                invitation: invitation.id,
                            })}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    aria-label={`Reenviar invitación de ${tenant.trade_name}`}
                                >
                                    {processing && <Spinner />}
                                    Reenviar
                                </Button>
                            )}
                        </Form>
                        <CancelInvitationDialog
                            tenant={tenant}
                            invitationId={invitation.id}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
