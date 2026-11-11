import { describe, expect, it } from 'vite-plus/test';

import { nextFocus } from '@/lib/org-chart/tree-keys';
import { emptyTree, reduce } from '@/lib/org-chart/tree-state';

// dir (open) leads a (closed, leads someone) and b (leads nobody); sola is another root.
const state = reduce(emptyTree, { type: 'toggle', id: 'dir' });
const parents = new Map([
    ['dir', null],
    ['a', 'dir'],
    ['b', 'dir'],
    ['sola', null],
]);
const tree = {
    visible: ['dir', 'a', 'b', 'sola'],
    parentOf: (id: string) => parents.get(id) ?? null,
    expandable: (id: string) => id === 'dir' || id === 'a',
    state,
};

describe('nextFocus', () => {
    it('moves down and up through the people on screen, and stays at the ends', () => {
        expect(nextFocus('ArrowDown', 'dir', tree)).toEqual({ focus: 'a' });
        expect(nextFocus('ArrowDown', 'sola', tree)).toEqual({ focus: 'sola' });
        expect(nextFocus('ArrowUp', 'b', tree)).toEqual({ focus: 'a' });
        expect(nextFocus('ArrowUp', 'dir', tree)).toEqual({ focus: 'dir' });
    });

    it('opens a closed person with the right arrow, and goes to the first report of an open one', () => {
        expect(nextFocus('ArrowRight', 'a', tree)).toEqual({
            focus: 'a',
            toggle: 'a',
        });
        expect(nextFocus('ArrowRight', 'dir', tree)).toEqual({ focus: 'a' });
        // Nobody below: nothing to open.
        expect(nextFocus('ArrowRight', 'b', tree)).toEqual({ focus: 'b' });
        // Open, and its reports still on their way.
        expect(
            nextFocus('ArrowRight', 'dir', {
                ...tree,
                visible: ['dir', 'sola'],
            }),
        ).toEqual({ focus: 'dir' });
    });

    it('closes an open person with the left arrow, and goes up to the manager from a closed one', () => {
        expect(nextFocus('ArrowLeft', 'dir', tree)).toEqual({
            focus: 'dir',
            toggle: 'dir',
        });
        expect(nextFocus('ArrowLeft', 'b', tree)).toEqual({ focus: 'dir' });
        expect(nextFocus('ArrowLeft', 'a', tree)).toEqual({ focus: 'dir' });
        expect(nextFocus('ArrowLeft', 'sola', tree)).toEqual({ focus: 'sola' });
    });

    it('jumps to the first and the last person on screen', () => {
        expect(nextFocus('Home', 'b', tree)).toEqual({ focus: 'dir' });
        expect(nextFocus('End', 'dir', tree)).toEqual({ focus: 'sola' });
    });

    it('opens or closes with Enter and the space bar whoever leads people', () => {
        expect(nextFocus('Enter', 'a', tree)).toEqual({
            focus: 'a',
            toggle: 'a',
        });
        expect(nextFocus(' ', 'dir', tree)).toEqual({
            focus: 'dir',
            toggle: 'dir',
        });
        expect(nextFocus('Enter', 'b', tree)).toEqual({ focus: 'b' });
    });

    it('leaves any other key alone, and a person who is not on screen', () => {
        expect(nextFocus('Tab', 'dir', tree)).toBeNull();
        expect(nextFocus('a', 'dir', tree)).toBeNull();
        expect(nextFocus('ArrowDown', 'nobody', tree)).toBeNull();
        expect(nextFocus('Home', 'dir', { ...tree, visible: [] })).toBeNull();
    });
});
