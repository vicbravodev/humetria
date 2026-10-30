import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/tenant/members';
import type { MemberRow, RoleOption } from '@/types';

/** Someone's role, changed in place: choosing another one saves it. */
export default function MemberRoleSelect({
    tenantSlug,
    member,
    roles,
}: {
    tenantSlug: string;
    member: MemberRow;
    roles: RoleOption[];
}) {
    return (
        <Select
            value={member.role}
            onValueChange={(role) =>
                router.put(
                    update.url({ tenant: tenantSlug, member: member.id }),
                    { role },
                    { preserveScroll: true },
                )
            }
        >
            <SelectTrigger
                size="sm"
                aria-label={`Rol de ${member.name}`}
                data-test="member-role"
                className="w-auto gap-2 px-3 data-[size=sm]:h-9"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {role.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
