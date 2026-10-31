import Initials from '@/components/humetria/initials';
import MemberRoleSelect from '@/components/tenancy/member-role-select';
import MemberRowActions from '@/components/tenancy/member-row-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import type { MemberRow as Member, RoleOption } from '@/types';

/**
 * Someone with access: their role, changed right there, and when they got in. The owner is changed from the
 * superadmin's console and nobody changes their own access, so neither row offers it.
 */
export default function MemberRow({
    tenantSlug,
    member,
    roles,
}: {
    tenantSlug: string;
    member: Member;
    roles: RoleOption[];
}) {
    const fixed = member.role === 'owner' || member.is_you;

    return (
        <TableRow>
            <TableCell className="whitespace-normal">
                <div className="flex items-center gap-2.5">
                    <Initials name={member.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">
                            {member.name}
                            {member.is_you && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (tú)
                                </span>
                            )}
                        </span>
                        <span className="text-[13px] leading-[18px] text-muted-foreground">
                            {member.email}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                {fixed ? (
                    <Badge
                        variant="secondary"
                        className="text-foreground before:hidden"
                    >
                        {member.role_label}
                    </Badge>
                ) : (
                    <MemberRoleSelect
                        tenantSlug={tenantSlug}
                        member={member}
                        roles={roles}
                    />
                )}
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                Desde el {member.since}
            </TableCell>
            <TableCell className="w-px text-right">
                {!fixed && (
                    <MemberRowActions tenantSlug={tenantSlug} member={member} />
                )}
            </TableCell>
        </TableRow>
    );
}
