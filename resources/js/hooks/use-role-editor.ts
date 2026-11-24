import { useForm } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { handOut } from '@/lib/permission-areas';
import {
    heldBy,
    keepsAcknowledgement,
    sensitiveAdded,
} from '@/lib/permission-groups';
import { store, update } from '@/routes/tenant/roles';
import type { PermissionGroup, RoleTemplateOption } from '@/types';

/**
 * A role while the owner names it and sets the level of each area; one send. A new role (blank, or from a template) and an
 * edited one differ only in where it goes and what counted as held before: for a new one, any sensitive permission is added.
 * The permissions are the state, so a set no level matches (or one of a tool the plan lost) travels untouched until its area
 * is changed.
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
        based_on: role === null ? from : null,
    });
    const errors: Partial<Record<string, string>> = form.errors;
    const change = (next: string[], basedOn: string | null) =>
        form.setData((data) => ({
            ...data,
            permissions: next,
            based_on: basedOn,
            // A different set of sensitive permissions asks for the confirmation again.
            acknowledge_sensitive:
                data.acknowledge_sensitive &&
                keepsAcknowledgement(groups, before, {
                    previous: data.permissions,
                    next,
                }),
        }));

    return {
        data: form.data,
        errors,
        processing: form.processing,
        sensitive: sensitiveAdded(groups, before, form.data.permissions),
        rename: (label: string) => form.setData('label', label),
        change: (next: string[]) => change(next, form.data.based_on),
        choose: (template: RoleTemplateOption | null) =>
            change(
                template === null ? [] : handOut(template.permissions, groups),
                template?.key ?? null,
            ),
        acknowledge: (checked: boolean) =>
            form.setData('acknowledge_sensitive', checked),
        save: () =>
            role === null
                ? form.post(store.url(tenantSlug))
                : form.put(update.url({ tenant: tenantSlug, role: role.id })),
    };
}
