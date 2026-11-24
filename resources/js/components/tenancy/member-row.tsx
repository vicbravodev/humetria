import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import MemberRoleSelect from '@/components/tenancy/member-role-select';
import MemberRowActions from '@/components/tenancy/member-row-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { edit } from '@/routes/tenant/members/permissions';
import type { MemberRow as Member, RoleOption } from '@/types';

/**
 * Someone with access: their role, changed right there, how many permissions they have besides it, and when they got in. The owner is changed from the
 * superadmin's console and nobody changes their own access, so neither row offers it.
 */
export default function MemberRow({
    member,
    roles,
}: {
    member: Member;
    roles: RoleOption[];
}) {
    const fixed = member.role === 'owner' || member.is_you;
    const tenantSlug = useTenantSlug();

    return (
        <TableRow>
            <TableCell className="whitespace-normal max-md:w-full">
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
                        <span className="text-caption text-muted-foreground">
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
                    <div className="flex flex-col items-start gap-1">
                        <MemberRoleSelect member={member} roles={roles} />
                        {member.extra_permissions > 0 && (
                            <Link
                                href={edit({
                                    tenant: tenantSlug,
                                    member: member.id,
                                })}
                                data-test="member-extras"
                                className="touch-hit text-caption font-medium text-primary underline-offset-4 hover:underline"
                            >
                                +
                                {countOf(
                                    member.extra_permissions,
                                    'permiso adicional',
                                    'permisos adicionales',
                                )}
                            </Link>
                        )}
                    </div>
                )}
            </TableCell>
            <TableCell className="text-sm text-muted-foreground">
                Desde el {member.since}
            </TableCell>
            <TableCell className="w-px text-right max-md:ml-auto max-md:w-auto">
                {!fixed && <MemberRowActions member={member} />}
            </TableCell>
        </TableRow>
    );
}
