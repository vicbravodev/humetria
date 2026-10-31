import { Form } from '@inertiajs/react';
import CancelInvitationDialog from '@/components/tenancy/cancel-invitation-dialog';
import TenantInvitationLines from '@/components/tenancy/tenant-invitation-lines';
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
        <div className="grid gap-2">
            {owner !== null && (
                <div className="flex flex-col">
                    <span>{owner.name}</span>
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        {owner.email}
                    </span>
                </div>
            )}
            {invitation !== null && (
                <div className="grid justify-items-start gap-1">
                    <TenantInvitationLines invitation={invitation} />
                    <div className="-ml-3 flex">
                        <Form
                            {...resend.form({
                                tenant: tenant.id,
                                invitation: invitation.id,
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
