import { usePage } from '@inertiajs/react';
import TenantInvitationActions from '@/components/tenancy/tenant-invitation-actions';
import TenantInvitationLines from '@/components/tenancy/tenant-invitation-lines';
import type { TenantRow } from '@/types';

/** Who owns the company, and the invitation still waiting for an answer, if any. */
export default function TenantOwnerCell({ tenant }: { tenant: TenantRow }) {
    const { auth } = usePage().props;
    const { owner, invitation } = tenant;

    if (owner === null && invitation === null) {
        return <p className="text-sm text-muted-foreground">Sin invitar</p>;
    }

    return (
        <div className="grid gap-2">
            {owner !== null && (
                <div className="flex flex-col">
                    <span>{owner.name}</span>
                    <span className="text-caption text-muted-foreground">
                        {owner.email}
                    </span>
                </div>
            )}
            {invitation !== null && (
                <div className="grid justify-items-start gap-1">
                    <TenantInvitationLines invitation={invitation} />
                    {auth.isStaff && (
                        <TenantInvitationActions
                            tenant={tenant}
                            invitationId={invitation.id}
                        />
                    )}
                </div>
            )}
        </div>
    );
}
