import TenantInvitationLines from '@/components/tenancy/tenant-invitation-lines';
import { Badge } from '@/components/ui/badge';
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
                <TenantInvitationLines invitation={invitation} />
            )}
        </div>
    );
}
