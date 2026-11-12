import { useEffect, useMemo, useReducer, useRef } from 'react';
import { moveInChart } from '@/lib/org-chart/chart-move';
import {
    initialChart,
    missingBranches,
    reduceChart,
    shown,
} from '@/lib/org-chart/chart-state';
import type {
    ChartAction,
    ChartInit,
    ChartState,
} from '@/lib/org-chart/chart-state';
import { reportsPage } from '@/lib/org-chart/reports-page';
import type { OrgNode } from '@/types';

/** Reports asked at once: opening everything of a large company must not open hundreds of connections. */
const PARALLEL = 6;

type TreeAction =
    | ChartAction
    | { type: 'move'; node: OrgNode; to: string | null };

/** The moves read the state they find, never the one a closure saw: an undo comes seconds after its change. */
function reducer(state: ChartState, action: TreeAction): ChartState {
    return action.type === 'move'
        ? moveInChart(state, { node: action.node, to: action.to })
        : reduceChart(state, action);
}

export type ChartTree = ReturnType<typeof useChartTree>;

/**
 * The chart on screen: who is open (by levels, or by hand), the reports loaded for each person (asked once per page,
 * when a branch is opened or «Ver más» is pressed) and the moves of the editing mode.
 */
export function useChartTree({
    init,
    reportsUrl,
}: {
    init: ChartInit;
    reportsUrl: (id: string, page: number) => string;
}) {
    const [base, dispatch] = useReducer(reducer, init, initialChart);
    const asked = useRef(new Set<string>());
    const state = useMemo(() => shown(base), [base]);

    const load = async (id: string, page: number) => {
        const key = `${id}:${page}`;

        if (asked.current.has(key)) {
            return;
        }

        asked.current.add(key);
        dispatch({ type: 'loading', id });

        try {
            const response = await fetch(reportsUrl(id, page), {
                headers: { Accept: 'application/json' },
            });
            const found = response.ok
                ? reportsPage(await response.json())
                : null;

            if (found === null) {
                throw new Error(`Reports request failed: ${id}`);
            }

            dispatch({ type: 'loaded', id, ...found });
        } catch {
            // Asked again when the person presses «Reintentar».
            asked.current.delete(key);
            dispatch({ type: 'failed', id });
        }
    };

    useEffect(() => {
        const running = [...state.branches.values()].filter(
            (branch) => branch.status === 'loading',
        ).length;

        missingBranches(state)
            .slice(0, Math.max(0, PARALLEL - running))
            .forEach((id) => void load(id, 1));
    });

    return {
        state,
        levels: base.levels,
        showLevels: (levels: number) => dispatch({ type: 'levels', levels }),
        toggle: (id: string) => dispatch({ type: 'toggle', id }),
        /** Opens a person (asking for their reports when they are not loaded), without closing anyone. */
        open: (id: string) =>
            dispatch({ type: 'expand', ids: [...state.expanded, id] }),
        /** Asks for the reports of a person without opening them: their panel lists them. */
        ensure: (id: string) => {
            if (
                !state.branches.has(id) &&
                (state.people.get(id)?.direct_reports ?? 0) > 0
            ) {
                void load(id, 1);
            }
        },
        loadMore: (id: string) =>
            void load(id, state.branches.get(id)?.nextPage ?? 1),
        /** Puts a person (with their team) under another, or under nobody. */
        move: (node: OrgNode, to: string | null) =>
            dispatch({ type: 'move', node, to }),
    };
}
