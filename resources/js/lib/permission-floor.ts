import {
    areaPermissions,
    floorOf,
    levelOf,
    summarize,
} from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';

/** The level an area holds: its own, or for a mixed set the highest wholly held under it. */
const heldIndex = (area: PermissionArea, held: string[]): number => {
    const index = area.levels.findIndex(
        (level) => level.key === levelOf(area, held),
    );

    return index === -1 ? floorOf(area, held) : index;
};

/**
 * What a person has besides their role (`base`): every permission the role does not bring, and the whole level of each area
 * raised past the role's. Kept whole, a raised area stays a clean level under another role (an invitation whose role changes).
 */
export function ownLevels({
    areas,
    held,
    base,
}: {
    areas: PermissionArea[];
    held: string[];
    base: string[];
}): string[] {
    const raised = areas.flatMap((area) => {
        const index = heldIndex(area, held);

        return index > floorOf(area, base)
            ? (area.levels[index]?.permissions ?? [])
            : [];
    });

    return held.filter((name) => !base.includes(name) || raised.includes(name));
}

/** «Además de lo que trae su rol, Vera administra Vacaciones»: only what goes past the role, never what it already gives. */
export function summarizeBeyond({
    areas,
    held,
    base,
    subject,
}: {
    areas: PermissionArea[];
    held: string[];
    base: string[];
    subject: string;
}): string {
    return summarize({
        areas,
        held: ownLevels({ areas, held, base }),
        subject,
    });
}

/**
 * Whether the role itself holds the area as a mix no level names (managing without seeing, from data saved before levels):
 * on a person's page there is no floor to raise from, so the area is shown locked and the role is what to change.
 */
export function roleMix(area: PermissionArea, base: string[]): boolean {
    return (
        areaPermissions(area).some((name) => base.includes(name)) &&
        levelOf(area, base) === null
    );
}
