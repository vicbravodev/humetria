import type { PermissionGroup, PermissionToggle } from '@/types/roles';

const all = (groups: PermissionGroup[]) =>
    groups.flatMap((group) => group.permissions);

/** The permissions held by the role, or by the person besides it, by name: what the switches start with. */
export function heldBy(
    groups: PermissionGroup[],
    source: 'role' | 'direct',
): string[] {
    return all(groups)
        .filter((permission) => permission.source === source)
        .map((permission) => permission.name);
}

/**
 * «2 de 3»: of the permissions of a tool the plan lets switch on, how many are on (chosen here, or locked on because the
 * person's role brings them). One of a tool the plan lacks counts for neither.
 */
export function groupCount(
    group: PermissionGroup,
    selected: string[],
    locked: string[] = [],
): { on: number; total: number } {
    const available = group.permissions.filter(
        (permission) => permission.unavailableReason === null,
    );

    return {
        on: available.filter(
            ({ name }) => selected.includes(name) || locked.includes(name),
        ).length,
        total: available.length,
    };
}

/**
 * The sensitive permissions switched on that were not held before, by label: what the owner confirms before saving (the
 * server asks for `acknowledge_sensitive` for exactly these). For a new role or an invitation, `before` is empty.
 */
export function sensitiveAdded(
    groups: PermissionGroup[],
    before: string[],
    after: string[],
): string[] {
    return all(groups)
        .filter(
            ({ name, sensitive }) =>
                sensitive && after.includes(name) && !before.includes(name),
        )
        .map((permission) => permission.label);
}

/** The chosen permissions with one switched on or off. */
export function toggled(
    selected: string[],
    name: string,
    on: boolean,
): string[] {
    const without = selected.filter((chosen) => chosen !== name);

    return on ? [...without, name] : without;
}

/**
 * How one switch shows: on only where the plan lets it be; still where the plan lacks its tool, the role brings it or the
 * screen is only read; and the reason under it, if any.
 */
export function switchLook(
    permission: PermissionToggle,
    state: { on: boolean; locked: boolean },
    readOnly: boolean,
): { on: boolean; still: boolean; hint: string | null } {
    const reason = permission.unavailableReason;

    if (reason !== null) {
        return { on: false, still: true, hint: reason };
    }

    return {
        on: state.on,
        still: state.locked || readOnly,
        hint: state.locked ? 'Viene de su rol' : null,
    };
}
