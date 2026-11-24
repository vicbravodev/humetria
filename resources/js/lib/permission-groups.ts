import type { PermissionGroup } from '@/types/roles';

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
 * Whether «Entiendo y confirmo» still holds after a switch: only while the sensitive permissions added are the same ones the
 * owner confirmed. Any change in that list asks again.
 */
export function keepsAcknowledgement(
    groups: PermissionGroup[],
    before: string[],
    { previous, next }: { previous: string[]; next: string[] },
): boolean {
    const confirmed = sensitiveAdded(groups, before, previous);
    const now = sensitiveAdded(groups, before, next);

    return (
        confirmed.length === now.length &&
        confirmed.every((label) => now.includes(label))
    );
}
