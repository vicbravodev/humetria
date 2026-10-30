import { describe, expect, it } from 'vite-plus/test';
import { chooseAll, chosenNames, idList, toggleChosen } from '@/lib/selection';

describe('toggleChosen', () => {
    it('adds once and removes', () => {
        expect(toggleChosen(['a'], 'b', true)).toEqual(['a', 'b']);
        expect(toggleChosen(['a'], 'a', true)).toEqual(['a']);
        expect(toggleChosen(['a', 'b'], 'a', false)).toEqual(['b']);
    });
});

describe('chooseAll', () => {
    it('adds the ids not chosen yet, keeping the order', () => {
        expect(chooseAll(['b'], ['a', 'b', 'c'])).toEqual(['b', 'a', 'c']);
    });
});

describe('idList', () => {
    it('keeps the strings of a list, and reads anything else as none', () => {
        expect(idList(['a', 1, 'b', null])).toEqual(['a', 'b']);
        expect(idList(undefined)).toEqual([]);
        expect(idList('a')).toEqual([]);
    });
});

describe('chosenNames', () => {
    it('names the chosen people seen so far, and counts the rest', () => {
        expect(
            chosenNames(['a', 'b', 'c'], { a: 'Ana', c: 'Carla', z: 'Zoé' }),
        ).toEqual({
            named: [
                { id: 'a', name: 'Ana' },
                { id: 'c', name: 'Carla' },
            ],
            unnamed: 1,
        });
        expect(chosenNames([], {})).toEqual({ named: [], unnamed: 0 });
    });
});
