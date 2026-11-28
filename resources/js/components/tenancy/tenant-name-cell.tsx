import { Link, usePage } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import TenantInvitationActions from '@/components/tenancy/tenant-invitation-actions';
import TenantStatus from '@/components/tenancy/tenant-status';
import { Badge } from '@/components/ui/badge';
import { TableCell } from '@/components/ui/table';
import { ownerLine } from '@/lib/tenant-work';
import { show } from '@/routes/companies';
import type { TenantRow } from '@/types';

/**
 * A company as its row names it (tablero Empresas): its name and one line under it, its RFC and who owns it. An invitation
 * still waiting shows there too, with its buttons; on a phone the status rides under the name as well.
 */
export default function TenantNameCell({
    tenant,
    mobile,
}: {
    tenant: TenantRow;
    mobile: boolean;
}) {
    const { auth } = usePage().props;
    const { owner, invitation } = tenant;

    return (
        <TableCell className="whitespace-normal max-md:px-2 md:min-w-64">
            <div className="flex items-center gap-3">
                <Initials name={tenant.trade_name} kind="company" />
                <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="flex flex-wrap items-center gap-2">
                        <Link
                            href={show(tenant.id)}
                            className="-my-2.5 py-2.5 font-medium underline-offset-4 hover:underline"
                        >
                            {tenant.trade_name}
                        </Link>
                        {tenant.type === 'union' && (
                            <Badge variant="outline">Sindicato</Badge>
                        )}
                    </span>
                    <span className="text-caption text-muted-foreground tabular-nums md:max-w-72 md:truncate">
                        {ownerLine(tenant.rfc, owner)}
                        {owner === null &&
                            invitation === null &&
                            ' · Sin invitar'}
                    </span>
                    {invitation !== null && (
                        <span
                            className={
                                invitation.status === 'pending'
                                    ? 'text-caption text-muted-foreground'
                                    : 'text-caption text-destructive'
                            }
                        >
                            {invitation.name} · {invitation.email} ·{' '}
                            {invitation.status_label}
                            {invitation.status === 'pending' &&
                                ` · vence el ${invitation.expires_on}`}
                        </span>
                    )}
                </div>
            </div>
            {invitation !== null && auth.isStaff && (
                <div className="mt-2 pl-12">
                    <TenantInvitationActions
                        tenant={tenant}
                        invitationId={invitation.id}
                    />
                </div>
            )}
            {mobile && (
                <div className="mt-3 pl-12">
                    <TenantStatus tenant={tenant} />
                </div>
            )}
        </TableCell>
    );
}
