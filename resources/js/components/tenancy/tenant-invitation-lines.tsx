import type { TenantInvitation } from '@/types';

/** The owner's invitation still waiting for an answer: who it went to and, while it is valid, when it stops being. */
export default function TenantInvitationLines({
    invitation,
}: {
    invitation: TenantInvitation;
}) {
    return (
        <div className="flex flex-col">
            <span>{invitation.name}</span>
            <span className="text-[13px] leading-[18px] text-muted-foreground">
                {invitation.email}
            </span>
            <span
                className={
                    invitation.status === 'pending'
                        ? 'text-[13px] leading-[18px] text-muted-foreground'
                        : 'text-[13px] leading-[18px] text-destructive'
                }
            >
                {invitation.status_label}
                {invitation.status === 'pending' &&
                    ` · vence el ${invitation.expires_on}`}
            </span>
        </div>
    );
}
