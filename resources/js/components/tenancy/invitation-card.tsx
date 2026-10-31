import Initials from '@/components/humetria/initials';
import { roleSummary } from '@/lib/roles';
import type { TenantRole } from '@/types/tenancy';

/** Who invites the person, to which company and as what (pantalla: Invitacion), above whatever the link asks. */
export default function InvitationCard({
    invitation,
}: {
    invitation: {
        inviter: string | null;
        trade_name: string;
        role: TenantRole;
        role_label: string;
    };
}) {
    const { inviter, trade_name: tradeName } = invitation;

    return (
        <div className="flex items-center gap-3.5 rounded-xl border bg-card p-4">
            <Initials
                name={inviter ?? tradeName}
                className="size-11 bg-accent text-sm text-primary"
            />
            <p className="flex min-w-0 flex-col">
                <span className="text-sm">
                    {inviter === null ? (
                        'Te invitaron a'
                    ) : (
                        <>
                            <strong className="font-semibold">{inviter}</strong>{' '}
                            te invitó a
                        </>
                    )}
                </span>
                <span className="font-semibold">{tradeName}</span>
                <span className="text-[13px] leading-[18px] text-muted-foreground">
                    como {invitation.role_label} ·{' '}
                    {roleSummary(invitation.role)}
                </span>
            </p>
        </div>
    );
}
