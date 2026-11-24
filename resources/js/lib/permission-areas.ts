import { AREAS } from '@/lib/permission-areas-data';
import type { AreaLevel, PermissionArea } from '@/lib/permission-areas-data';
import type { PermissionGroup, PermissionToggle } from '@/types';

export { AREAS, SECTIONS, TEMPLATE_HINTS } from '@/lib/permission-areas-data';
export type {
    AreaExtra,
    AreaLevel,
    PermissionArea,
} from '@/lib/permission-areas-data';

/** Every permission the levels of an area give (its extras apart). */
export const areaPermissions = (area: PermissionArea): string[] => [
    ...new Set(area.levels.flatMap((level) => level.permissions)),
];

const sameSet = (a: string[], b: string[]): boolean =>
    a.length === b.length && a.every((name) => b.includes(name));

const toggles = (groups: PermissionGroup[]): PermissionToggle[] =>
    groups.flatMap((group) => group.permissions);

/**
 * The areas this account can hand out, in order, each with the reason it is locked when the plan lacks its tool (null when
 * it is open). An area none of whose permissions the account knows (a union's NOM-035) is left out, and so is an extra.
 */
export function areasFor(
    groups: PermissionGroup[],
): { area: PermissionArea; reason: string | null }[] {
    const known = new Map(
        toggles(groups).map((toggle) => [toggle.name, toggle]),
    );

    return AREAS.flatMap((area) => {
        const present = areaPermissions(area).flatMap(
            (name) => known.get(name) ?? [],
        );
        const first = present[0];

        if (first === undefined) {
            return [];
        }

        const locked = present.every(
            (toggle) => toggle.unavailableReason !== null,
        );

        return [
            {
                area: {
                    ...area,
                    extras: area.extras.filter((extra) =>
                        known.has(extra.permission),
                    ),
                },
                reason: locked ? first.unavailableReason : null,
            },
        ];
    });
}

/** Of some permissions (a template's), those this account can hand out now: known to it and of a tool its plan has. */
export function handOut(names: string[], groups: PermissionGroup[]): string[] {
    const open = toggles(groups)
        .filter((toggle) => toggle.unavailableReason === null)
        .map((toggle) => toggle.name);

    return names.filter((name) => open.includes(name));
}

/** The level of an area whose permissions are exactly those held there, or null when none is (a set saved before levels). */
export function levelOf(area: PermissionArea, held: string[]): string | null {
    const inArea = areaPermissions(area).filter((name) => held.includes(name));

    return (
        area.levels.find((level) => sameSet(level.permissions, inArea))?.key ??
        null
    );
}

/** The position of the highest level whose permissions are all held: what a person's role already gives them. */
export function floorOf(area: PermissionArea, held: string[]): number {
    return area.levels.reduce(
        (floor, level, index) =>
            level.permissions.every((name) => held.includes(name))
                ? index
                : floor,
        0,
    );
}

/** The permissions with an area set to a level: its level permissions swapped, its extras and every other kept. */
export function withLevel({
    selected,
    area,
    level,
}: {
    selected: string[];
    area: PermissionArea;
    level: string;
}): string[] {
    const own = areaPermissions(area);
    const chosen = area.levels.find((candidate) => candidate.key === level);

    return [
        ...selected.filter((name) => !own.includes(name)),
        ...(chosen?.permissions ?? []),
    ];
}

/** The line under an area: what its level lets do, or, for a set no level matches, what it holds and what to do. */
export function phraseOf(
    area: PermissionArea,
    held: string[],
    groups: PermissionGroup[],
): string {
    const key = levelOf(area, held);
    const level = area.levels.find((candidate) => candidate.key === key);

    if (level !== undefined) {
        return level.phrase;
    }

    const labels = new Map(
        toggles(groups).map((toggle) => [toggle.name, toggle.label]),
    );
    const names = areaPermissions(area)
        .filter((name) => held.includes(name))
        .map((name) => labels.get(name) ?? name);

    return `Tiene una combinación propia: ${names.join(', ')}. Elige un nivel para ordenarla.`;
}

/** Whether a level (or an extra) opens confidential data: any of its permissions is sensitive. */
export function sensitiveLevel(
    level: { permissions: string[] } | undefined,
    groups: PermissionGroup[],
): boolean {
    const sensitive = toggles(groups)
        .filter((toggle) => toggle.sensitive)
        .map((toggle) => toggle.name);

    return (level?.permissions ?? []).some((name) => sensitive.includes(name));
}

/** «A», «A y B», «A, B y C». */
const listed = (names: string[]): string =>
    names.length < 2
        ? names.join('')
        : `${names.slice(0, -1).join(', ')} y ${names.at(-1) ?? ''}`;

/** Up to three names, then how many more: «A, B, C y 4 áreas más». */
function shortList(names: string[]): string {
    if (names.length <= 3) {
        return listed(names);
    }

    const more = names.length - 3;

    return `${names.slice(0, 3).join(', ')} y ${more} ${more === 1 ? 'área más' : 'áreas más'}`;
}

/** The level an area reads as in a summary: its own, or for a mixed set the highest wholly held under it. */
const readLevel = (
    area: PermissionArea,
    held: string[],
): AreaLevel | undefined =>
    area.levels.find((level) => level.key === levelOf(area, held)) ??
    area.levels[floorOf(area, held)];

/**
 * What a set of permissions lets do, in one plain sentence (spec: «resumen en lenguaje llano»): «Quien tenga este rol administra
 * Vacaciones y ve NOM-035, NOM-037, Asistencia y 4 áreas más. También atiende quejas asignadas.»
 */
export function summarize({
    areas,
    held,
    subject,
}: {
    areas: PermissionArea[];
    held: string[];
    subject: string;
}): string {
    const levels = areas.map((area) => ({
        area,
        level: readLevel(area, held),
    }));
    const named = (key: string): string[] =>
        levels
            .filter(({ level }) => level?.key === key && level.summary === null)
            .map(({ area }) => area.short);
    const lists: [string, string[]][] = [
        ['administra', named('manage')],
        ['ve', named('view')],
    ];
    const verbs = lists.flatMap(([verb, names]) =>
        names.length > 0 ? [`${verb} ${shortList(names)}`] : [],
    );
    const others = [
        ...levels.flatMap(({ level }) => level?.summary ?? []),
        ...areas.flatMap((area) =>
            area.extras
                .filter((extra) => held.includes(extra.permission))
                .map((extra) => extra.summary),
        ),
    ];

    return sentence(subject, verbs, others);
}

function sentence(subject: string, verbs: string[], others: string[]): string {
    if (verbs.length === 0) {
        return others.length === 0
            ? `${subject} no tiene acceso a ninguna área.`
            : `${subject} ${listed(others)}.`;
    }

    const [first = '', second] = verbs;
    const main =
        second === undefined
            ? first
            : `${first}${first.includes(' y ') ? ', y' : ' y'} ${second}`;

    return others.length === 0
        ? `${subject} ${main}.`
        : `${subject} ${main}. También ${listed(others)}.`;
}

/** How many of the areas shown have any access, a mixed one included: «10 de 14». */
export function accessCount(
    areas: PermissionArea[],
    held: string[],
): { on: number; total: number } {
    return {
        on: areas.filter((area) =>
            areaPermissions(area).some((name) => held.includes(name)),
        ).length,
        total: areas.length,
    };
}
