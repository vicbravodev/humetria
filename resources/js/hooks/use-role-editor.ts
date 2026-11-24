import { useForm } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { heldBy, sensitiveAdded, toggled } from '@/lib/permission-groups';
import { store, update } from '@/routes/tenant/roles';
import type { PermissionGroup } from '@/types';

/**
 * A role while the owner names it and switches its permissions; one send. A new role (blank, or copied from a template) and an
 * edited one differ only in where it goes and what counted as held before: for a new one, any sensitive permission is added.
 */
export function useRoleEditor(
    role: { id: number; label: string } | null,
    from: string | null,
    groups: PermissionGroup[],
) {
    const tenantSlug = useTenantSlug();
    const before = role === null ? [] : heldBy(groups, 'role');
    const form = useForm({
        label: role?.label ?? '',
        permissions: heldBy(groups, 'role'),
        acknowledge_sensitive: false,
        ...(role === null && from !== null ? { based_on: from } : {}),
    });
    const errors: Partial<Record<string, string>> = form.errors;

    return {
        data: form.data,
        errors,
        processing: form.processing,
        sensitive: sensitiveAdded(groups, before, form.data.permissions),
        rename: (label: string) => form.setData('label', label),
        toggle: (name: string, on: boolean) =>
            form.setData(
                'permissions',
                toggled(form.data.permissions, name, on),
            ),
        acknowledge: (checked: boolean) =>
            form.setData('acknowledge_sensitive', checked),
        save: () =>
            role === null
                ? form.post(store.url(tenantSlug))
                : form.put(update.url({ tenant: tenantSlug, role: role.id })),
    };
}
