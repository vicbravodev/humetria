import { useForm } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    heldBy,
    keepsAcknowledgement,
    sensitiveAdded,
} from '@/lib/permission-groups';
import { update } from '@/routes/tenant/members/permissions';
import type { PermissionGroup } from '@/types';

/**
 * A person's permissions besides their role, while the owner raises the level of some areas; one send. What the role brings is
 * the floor and is never sent: only the person's own go, so bringing every area back to the role's level sends an empty list.
 */
export function useMemberPermissions(
    memberId: number,
    groups: PermissionGroup[],
) {
    const tenantSlug = useTenantSlug();
    const base = heldBy(groups, 'role');
    const before = heldBy(groups, 'direct');
    const form = useForm({ permissions: before, acknowledge_sensitive: false });
    const errors: Partial<Record<string, string>> = form.errors;

    return {
        data: form.data,
        errors,
        processing: form.processing,
        base,
        held: [...base, ...form.data.permissions],
        sensitive: sensitiveAdded(groups, before, form.data.permissions),
        /** Every permission in play with one area changed: what goes past the role is the person's own. */
        change: (held: string[]) => {
            const next = held.filter((name) => !base.includes(name));

            form.setData((data) => ({
                ...data,
                permissions: next,
                // A different set of sensitive permissions asks for the confirmation again.
                acknowledge_sensitive:
                    data.acknowledge_sensitive &&
                    keepsAcknowledgement(groups, before, {
                        previous: data.permissions,
                        next,
                    }),
            }));
        },
        acknowledge: (checked: boolean) =>
            form.setData('acknowledge_sensitive', checked),
        save: () =>
            form.put(update.url({ tenant: tenantSlug, member: memberId })),
    };
}
