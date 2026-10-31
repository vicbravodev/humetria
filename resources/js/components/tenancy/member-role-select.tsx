import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/tenant/members';
import type { MemberRow, RoleOption } from '@/types';

/**
 * Someone's role, changed in place: choosing another one saves it. While it saves, the field shows the role chosen,
 * busy, and takes no other choice.
 */
export default function MemberRoleSelect({
    tenantSlug,
    member,
    roles,
}: {
    tenantSlug: string;
    member: MemberRow;
    roles: RoleOption[];
}) {
    const [saving, setSaving] = useState<string | null>(null);

    return (
        <Select
            value={saving ?? member.role}
            disabled={saving !== null}
            onValueChange={(role) =>
                router.put(
                    update.url({ tenant: tenantSlug, member: member.id }),
                    { role },
                    {
                        preserveScroll: true,
                        onStart: () => setSaving(role),
                        onFinish: () => setSaving(null),
                    },
                )
            }
        >
            <SelectTrigger
                size="sm"
                aria-label={`Rol de ${member.name}`}
                aria-busy={saving !== null}
                data-test="member-role"
                className="w-auto gap-2 px-3 data-[size=sm]:h-9"
            >
                {saving !== null && <Spinner />}
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
