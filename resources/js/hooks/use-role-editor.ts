import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { afterRender, revealFirstError } from '@/lib/focus';
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
 * is changed. A template chosen while editing replaces the levels in the form only: the role keeps its own `based_on`.
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
    // Editing: the template chosen to start from (null: from scratch), undefined while none has been.
    const [started, setStarted] = useState<string | null | undefined>();
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
        /** The template the cards show as chosen: the form's own for a new role, the one chosen here when editing. */
        preset: role === null ? form.data.based_on : started,
        choose: (template: RoleTemplateOption | null) => {
            setStarted(template?.key ?? null);
            change(
                template === null ? [] : handOut(template.permissions, groups),
                role === null ? (template?.key ?? null) : form.data.based_on,
            );
        },
        acknowledge: (checked: boolean) =>
            form.setData('acknowledge_sensitive', checked),
        /** Sends it; a refusal brings the first error into view, which on a phone may be far from the button. */
        save: (root: HTMLFormElement) => {
            const options = {
                onError: () => afterRender(() => revealFirstError(root)),
            };

            if (role === null) {
                form.post(store.url(tenantSlug), options);
            } else {
                form.put(
                    update.url({ tenant: tenantSlug, role: role.id }),
                    options,
                );
            }
        },
    };
}
