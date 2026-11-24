import { useForm } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    heldBy,
    keepsAcknowledgement,
    sensitiveAdded,
    toggled,
} from '@/lib/permission-groups';
import { update } from '@/routes/tenant/members/permissions';
import type { PermissionGroup } from '@/types';

/**
 * A person's permissions besides their role, while the owner switches them; one send. What the role brings is shown on and
 * locked, and never sent: only the person's own go, so switching all of them off sends an empty list.
 */
export function useMemberPermissions(
    memberId: number,
    groups: PermissionGroup[],
) {
    const tenantSlug = useTenantSlug();
    const before = heldBy(groups, 'direct');
    const form = useForm({ permissions: before, acknowledge_sensitive: false });
    const errors: Partial<Record<string, string>> = form.errors;

    return {
        data: form.data,
        errors,
        processing: form.processing,
        locked: heldBy(groups, 'role'),
        sensitive: sensitiveAdded(groups, before, form.data.permissions),
        toggle: (name: string, on: boolean) => {
            const next = toggled(form.data.permissions, name, on);

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
