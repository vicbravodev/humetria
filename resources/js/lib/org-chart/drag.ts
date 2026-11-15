import { ancestorsOf, parentsOf } from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';

/**
 * What dropping a person on a manager would do: `valid`, or why not. The server has the last word (it also refuses a
 * manager who left); this is what lets the chart say it before the person lets go.
 */
export type DropState = 'valid' | 'self' | 'descendant' | 'current';

export function dropState(
    state: ChartState,
    draggedId: string,
    targetId: string,
): DropState {
    if (draggedId === targetId) {
        return 'self';
    }

    if (parentsOf(state).get(draggedId) === targetId) {
        return 'current';
    }

    return ancestorsOf(state, targetId).includes(draggedId)
        ? 'descendant'
        : 'valid';
}

export type Rect = {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
};

/** The card under a point, other than the one being dragged; null over the gaps and the empty canvas. */
export function targetAt(
    point: { x: number; y: number },
    rects: Rect[],
    dragged: string,
): string | null {
    return (
        rects.find(
            (rect) =>
                rect.id !== dragged &&
                point.x >= rect.x &&
                point.x <= rect.x + rect.width &&
                point.y >= rect.y &&
                point.y <= rect.y + rect.height,
        )?.id ?? null
    );
}

/** What to tell whoever dropped a person where they cannot go; null when they can. */
export function dropMessage(
    state: DropState,
    names: { dragged: string; target: string },
): string | null {
    switch (state) {
        case 'descendant':
            return `No se puede: ${names.target} reporta a ${names.dragged}.`;
        case 'self':
            return 'Una persona no puede ser su propio jefe.';
        case 'current':
            return `${names.target} ya es el jefe de ${names.dragged}.`;
        case 'valid':
            return null;
    }
}
