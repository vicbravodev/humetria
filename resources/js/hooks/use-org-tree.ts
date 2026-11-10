import type { KeyboardEvent } from 'react';
import { useReducer, useRef, useState } from 'react';
import { nextFocus } from '@/lib/org-chart/tree-keys';
import type { TreeState } from '@/lib/org-chart/tree-state';
import {
    parentsOf,
    reduce,
    reportsPage,
    visibleIds,
} from '@/lib/org-chart/tree-state';
import type { OrgNode } from '@/types';

/** The DOM id of a person of the tree, to move the focus to them. */
export const orgNodeId = (id: string) => `org-node-${id}`;

/** The tree as its components read it. */
export type OrgTreeView = ReturnType<typeof useOrgTree>;

/**
 * The org chart on screen: who is open, the reports loaded for each person (asked once per person and page, when
 * they are opened or «Ver más» is pressed) and the person the keyboard is on.
 */
export function useOrgTree({
    roots,
    initial,
    start,
    reportsUrl,
}: {
    roots: OrgNode[];
    /** What is already open when the page arrives: the way down to a focused person. */
    initial: TreeState;
    /** The person the keyboard starts on. */
    start: string | undefined;
    reportsUrl: (id: string, page: number) => string;
}) {
    const [state, dispatch] = useReducer(reduce, initial);
    const [active, setActive] = useState(start ?? roots[0]?.id ?? '');
    const asked = useRef(new Set<string>());

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

    const toggle = (id: string) => {
        dispatch({ type: 'toggle', id });
        setActive(id);

        if (!state.children.has(id)) {
            void load(id, 1);
        }
    };

    const expandable = (node: OrgNode) =>
        node.direct_reports > 0 ||
        (state.children.get(node.id)?.nodes.length ?? 0) > 0;

    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        // Only the keys pressed on a person: a button inside the tree keeps its own.
        if (
            !(event.target instanceof HTMLElement) ||
            event.target.role !== 'treeitem'
        ) {
            return;
        }

        const people = new Map(
            [
                ...roots,
                ...[...state.children.values()].flatMap(({ nodes }) => nodes),
            ].map((node) => [node.id, node]),
        );
        const parents = parentsOf(roots, state);
        const move = nextFocus(event.key, active, {
            visible: visibleIds(roots, state),
            parentOf: (id) => parents.get(id) ?? null,
            expandable: (id) => {
                const node = people.get(id);

                return node !== undefined && expandable(node);
            },
            state,
        });

        if (move === null) {
            return;
        }

        event.preventDefault();

        if (move.toggle !== undefined) {
            toggle(move.toggle);
        }

        setActive(move.focus);
        document.getElementById(orgNodeId(move.focus))?.focus();
    };

    return {
        state,
        active,
        setActive,
        expandable,
        toggle,
        /** The next page of a person's reports, or the one that failed again. */
        loadMore: (id: string) =>
            void load(id, state.children.get(id)?.nextPage ?? 1),
        onKeyDown,
    };
}
