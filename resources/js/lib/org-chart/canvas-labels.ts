import { countOf } from '@/lib/plural';
import type { OrgNode } from '@/types';

/** What a screen reader says of a card: "Esther Alaniz Segundo, Almacenista, 3 directos, 78 en total". */
export function nodeLabel(
    node: Pick<
        OrgNode,
        'name' | 'job_title' | 'direct_reports' | 'total_reports'
    >,
): string {
    const team =
        node.direct_reports === 0
            ? 'sin equipo'
            : `${countOf(node.direct_reports, 'directo', 'directos')}, ${node.total_reports} en total`;

    return [node.name, node.job_title, team]
        .filter((part) => part !== '')
        .join(', ');
}

/** The pill under a card: what hides below a closed branch, the direct reports of an open one. */
export function pillText(
    node: Pick<OrgNode, 'direct_reports' | 'total_reports'>,
    open: boolean,
): string {
    return open
        ? countOf(node.direct_reports, 'directo', 'directos')
        : `+${node.direct_reports} · ${node.total_reports} en total`;
}

/** The facts under the title: people, levels and how many have a manager. */
export function factTexts({
    people,
    levels,
    withManager,
}: {
    people: number;
    levels: number;
    withManager: number;
}): string[] {
    return [
        countOf(people, 'persona activa', 'personas activas'),
        countOf(levels, 'nivel', 'niveles'),
        withManager === 0
            ? 'Ninguna con jefe directo'
            : `${withManager} con jefe directo`,
    ];
}

/** The amber ribbon: several people at the top, and whoever the chart does not draw for leading nobody. */
export function headsRibbon(heads: number, unplaced: number): string {
    if (heads > 1) {
        const top = `${heads} personas sin jefe: normalmente hay una`;

        return unplaced === 0
            ? top
            : `${top}. Otras ${unplaced} no tienen jefe ni equipo y no se dibujan`;
    }

    return unplaced === 1
        ? 'Otra persona no tiene jefe ni equipo y no se dibuja'
        : `Otras ${unplaced} personas no tienen jefe ni equipo y no se dibujan`;
}

/** How many levels «Mostrar» can open; the last is all of them. */
export const DEPTHS = [1, 2, 3, 4, Infinity];

export function depthLabel(levels: number): string {
    return Number.isFinite(levels)
        ? `Mostrar: ${countOf(levels, 'nivel', 'niveles')}`
        : 'Mostrar: todos';
}

/** The toast after a change: who reports to whom now. */
export function undoText(name: string, manager: string | null): string {
    return manager === null
        ? `${name} se quedó sin jefe directo`
        : `${name} ahora reporta a ${manager}`;
}
