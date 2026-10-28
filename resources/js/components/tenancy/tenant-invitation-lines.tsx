import { Badge } from '@/components/ui/badge';
import type { TenantInvitation } from '@/types';

/** The invitation still waiting for an answer: who it went to and, while it is valid, when it stops being. */
export default function TenantInvitationLines({
    invitation,
}: {
    invitation: TenantInvitation;
}) {
    return (
        <div className="grid justify-items-start gap-1">
            <Badge
                variant={
                    invitation.status === 'pending'
                        ? 'secondary'
                        : 'destructive'
                }
            >
                {invitation.status_label}
            </Badge>
            <p className="text-sm text-muted-foreground">
                {invitation.name} · {invitation.email}
            </p>
            {invitation.status === 'pending' && (
                <p className="text-sm text-muted-foreground">
                    Vence el {invitation.expires_on}
                </p>
            )}
        </div>
    );
}
