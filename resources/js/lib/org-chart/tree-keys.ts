import type { TreeState } from '@/lib/org-chart/tree-state';

/** What the keyboard needs to know of the tree: who is on screen, who each reports to and who leads people. */
export type KeyTree = {
    visible: string[];
    parentOf: (id: string) => string | null;
    expandable: (id: string) => boolean;
    state: TreeState;
};

type Move = { focus: string; toggle?: string };

function sideways(key: string, current: string, tree: KeyTree): Move {
    const open = tree.state.expanded.has(current);

    if (key === 'ArrowLeft') {
        return open
            ? { focus: current, toggle: current }
            : { focus: tree.parentOf(current) ?? current };
    }

    if (!open) {
        return tree.expandable(current)
            ? { focus: current, toggle: current }
            : { focus: current };
    }

    const below = tree.visible[tree.visible.indexOf(current) + 1];

    return {
        focus:
            below !== undefined && tree.parentOf(below) === current
                ? below
                : current,
    };
}

/**
 * Where a key takes the focus of the tree, and whom it opens or closes (the Tree View pattern of WAI-ARIA): up and
 * down move through the people on screen; right opens or goes to the first report; left closes or goes up to the
 * manager; Home and End jump to the ends; Enter and the space bar open or close. Null for a key the tree leaves alone.
 */
export function nextFocus(
    key: string,
    current: string,
    tree: KeyTree,
): Move | null {
    const index = tree.visible.indexOf(current);

    if (index === -1) {
        return null;
    }

    const last = tree.visible.length - 1;
    const jumps = new Map([
        ['ArrowDown', index + 1],
        ['ArrowUp', index - 1],
        ['Home', 0],
        ['End', last],
    ]);
    const jump = jumps.get(key);

    if (jump !== undefined) {
        return { focus: tree.visible[jump] ?? current };
    }

    if (key === 'ArrowLeft' || key === 'ArrowRight') {
        return sideways(key, current, tree);
    }

    if (key === 'Enter' || key === ' ') {
        return tree.expandable(current)
            ? { focus: current, toggle: current }
            : { focus: current };
    }

    return null;
}
