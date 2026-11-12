import { describe, expect, it } from 'vite-plus/test';

import {
    ancestorsOf,
    initialChart,
    levelsToExpand,
    missingBranches,
    parentsOf,
    reduceChart,
    teamIds,
} from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';
import type { OrgNode } from '@/types';

const node = (id: string, extra: Partial<OrgNode> = {}): OrgNode => ({
    id,
    name: id,
    job_title: '',
    area: '',
    work_center: '',
    active: true,
    direct_reports: 0,
    total_reports: 0,
    flag: null,
    matches: true,
    manager_inactive: false,
    absent_today: false,
    ...extra,
});

const sorted = (ids: string[]) => ids.toSorted((a, b) => a.localeCompare(b));

// dir leads gte (who leads ana and beto) and sofia; lola leads nobody.
const dir = node('dir', { direct_reports: 2, total_reports: 4 });
const gte = node('gte', { direct_reports: 2, total_reports: 2 });
const sofia = node('sofia');
const ana = node('ana');
const beto = node('beto');
const lola = node('lola');

function chart(): ChartState {
    return initialChart({
        roots: [dir, lola],
        firstLevel: { dir: [gte, sofia] },
        chain: [],
        focusChildren: null,
    });
}

describe('initialChart', () => {
    it('opens the roots with their first level, and knows nobody else', () => {
        const state = chart();

        expect(state.roots).toEqual(['dir', 'lola']);
        expect(state.branches.get('dir')).toEqual({
            ids: ['gte', 'sofia'],
            nextPage: null,
            status: 'ready',
        });
        expect([...state.expanded]).toEqual(['dir']);
        expect(state.people.get('gte')?.direct_reports).toBe(2);
        expect(state.branches.has('lola')).toBe(false);
    });

    it('offers the next page of a root whose first level is not all there', () => {
        const state = initialChart({
            roots: [node('big', { direct_reports: 60 })],
            firstLevel: {
                big: Array.from({ length: 50 }, (_, i) => node(`p${i}`)),
            },
            chain: [],
            focusChildren: null,
        });

        expect(state.branches.get('big')?.nextPage).toBe(2);
    });

    it('opens the chain of a focused person with one report per level, and their own reports', () => {
        const state = initialChart({
            roots: [dir],
            firstLevel: { dir: [gte, sofia] },
            chain: [dir, gte, ana],
            focusChildren: { data: [], current_page: 1, last_page: 1 },
        });

        expect(sorted([...state.expanded])).toEqual(['dir', 'gte']);
        expect(state.branches.get('gte')).toEqual({
            ids: ['ana'],
            nextPage: 1,
            status: 'ready',
        });
        expect(state.branches.get('dir')?.ids).toEqual(['gte', 'sofia']);
        expect(state.people.get('ana')).toEqual(ana);
    });

    it('opens the reports of the focused person from their first page', () => {
        const boss = node('boss', { direct_reports: 3 });
        const state = initialChart({
            roots: [dir],
            firstLevel: {},
            chain: [dir, boss],
            focusChildren: {
                data: [ana, beto],
                current_page: 1,
                last_page: 2,
            },
        });

        expect(state.branches.get('boss')).toEqual({
            ids: ['ana', 'beto'],
            nextPage: 2,
            status: 'ready',
        });
        expect(state.expanded.has('boss')).toBe(true);
    });

    it('draws the top of a chain that is not among the roots of the page', () => {
        const state = initialChart({
            roots: [lola],
            firstLevel: {},
            chain: [dir, gte],
            focusChildren: { data: [], current_page: 1, last_page: 1 },
        });

        expect(state.roots).toEqual(['dir', 'lola']);
    });

    it('does not count as shown a report of the chain who left', () => {
        const left = node('left', { active: false });
        const state = initialChart({
            roots: [dir],
            firstLevel: {},
            chain: [dir, left],
            focusChildren: null,
        });

        expect(state.branches.get('dir')).toEqual({
            ids: [],
            nextPage: 1,
            status: 'ready',
        });
    });
});

describe('reduceChart', () => {
    it('opens and closes a person', () => {
        const opened = reduceChart(chart(), { type: 'toggle', id: 'gte' });
        const closed = reduceChart(opened, { type: 'toggle', id: 'gte' });

        expect(opened.expanded.has('gte')).toBe(true);
        expect(closed.expanded.has('gte')).toBe(false);
    });

    it('marks the reports of a person as loading, keeping what it had', () => {
        const state = reduceChart(chart(), { type: 'loading', id: 'dir' });

        expect(state.branches.get('dir')).toEqual({
            ids: ['gte', 'sofia'],
            nextPage: null,
            status: 'loading',
        });
        expect(
            reduceChart(chart(), { type: 'loading', id: 'gte' }).branches.get(
                'gte',
            ),
        ).toEqual({ ids: [], nextPage: null, status: 'loading' });
    });

    it('adds a page of reports without repeating anyone', () => {
        const state = reduceChart(chart(), {
            type: 'loaded',
            id: 'gte',
            nodes: [ana, beto],
            nextPage: 2,
        });
        const again = reduceChart(state, {
            type: 'loaded',
            id: 'gte',
            nodes: [beto, node('zoe')],
            nextPage: null,
        });

        expect(state.branches.get('gte')?.ids).toEqual(['ana', 'beto']);
        expect(again.branches.get('gte')).toEqual({
            ids: ['ana', 'beto', 'zoe'],
            nextPage: null,
            status: 'ready',
        });
        expect(again.people.get('zoe')?.name).toBe('zoe');
    });

    it('remembers that the reports could not be loaded', () => {
        const state = reduceChart(chart(), { type: 'failed', id: 'gte' });

        expect(state.branches.get('gte')?.status).toBe('error');
    });

    it('opens exactly the people it is told, and closes everybody on an empty list', () => {
        const opened = reduceChart(chart(), {
            type: 'expand',
            ids: ['dir', 'gte'],
        });

        expect([...opened.expanded]).toEqual(['dir', 'gte']);
        expect(
            reduceChart(opened, { type: 'expand', ids: [] }).expanded.size,
        ).toBe(0);
    });
});

describe('parentsOf, ancestorsOf and teamIds', () => {
    const state = reduceChart(chart(), {
        type: 'loaded',
        id: 'gte',
        nodes: [ana, beto],
        nextPage: null,
    });

    it('tells who each loaded person reports to; null for a root', () => {
        const parents = parentsOf(state);

        expect(parents.get('ana')).toBe('gte');
        expect(parents.get('gte')).toBe('dir');
        expect(parents.get('dir')).toBeNull();
        expect(parents.get('lola')).toBeNull();
    });

    it('lists the people above one, from the top', () => {
        expect(ancestorsOf(state, 'ana')).toEqual(['dir', 'gte']);
        expect(ancestorsOf(state, 'dir')).toEqual([]);
        expect(ancestorsOf(state, 'nobody')).toEqual([]);
    });

    it('lists a person with everybody loaded below them', () => {
        expect(sorted(teamIds(state, 'dir'))).toEqual([
            'ana',
            'beto',
            'dir',
            'gte',
            'sofia',
        ]);
        expect(teamIds(state, 'lola')).toEqual(['lola']);
    });
});

describe('levelsToExpand and missingBranches', () => {
    const state = reduceChart(chart(), {
        type: 'loaded',
        id: 'gte',
        nodes: [ana, beto],
        nextPage: null,
    });

    it('opens whoever leads people above the level asked, counting the roots as the first', () => {
        expect(levelsToExpand(state, 1)).toEqual([]);
        expect(levelsToExpand(state, 2)).toEqual(['dir']);
        expect(sorted(levelsToExpand(state, 3))).toEqual(['dir', 'gte']);
        expect(sorted(levelsToExpand(state, Infinity))).toEqual(['dir', 'gte']);
    });

    it('says whose reports are open and not loaded yet', () => {
        const open = reduceChart(chart(), {
            type: 'expand',
            ids: ['dir', 'gte'],
        });

        expect(missingBranches(open)).toEqual(['gte']);
        expect(missingBranches(state)).toEqual([]);
    });

    it('does not ask again for the reports that are loading or failed', () => {
        const open = reduceChart(chart(), { type: 'expand', ids: ['gte'] });

        expect(
            missingBranches(reduceChart(open, { type: 'loading', id: 'gte' })),
        ).toEqual([]);
        expect(
            missingBranches(reduceChart(open, { type: 'failed', id: 'gte' })),
        ).toEqual([]);
    });
});
