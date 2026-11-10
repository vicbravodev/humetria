import { describe, expect, it } from 'vite-plus/test';

import {
    emptyTree,
    focusedTree,
    parentsOf,
    reduce,
    reportsPage,
    visibleIds,
} from '@/lib/org-chart/tree-state';
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
    ...extra,
});

const page = (nodes: OrgNode[], current = 1, last = 1) => ({
    data: nodes,
    total: nodes.length,
    from: 1,
    to: nodes.length,
    current_page: current,
    last_page: last,
    prev_page_url: null,
    next_page_url: null,
});

describe('reduce', () => {
    it('opens and closes a person', () => {
        const open = reduce(emptyTree, { type: 'toggle', id: 'dir' });

        expect([...open.expanded]).toEqual(['dir']);
        expect([
            ...reduce(open, { type: 'toggle', id: 'dir' }).expanded,
        ]).toEqual([]);
        // The state it was given is never changed.
        expect([...emptyTree.expanded]).toEqual([]);
    });

    it('marks the reports of a person as loading, keeping what it had', () => {
        const loading = reduce(emptyTree, { type: 'loading', id: 'dir' });

        expect(loading.children.get('dir')).toEqual({
            nodes: [],
            nextPage: null,
            status: 'loading',
        });

        const loaded = reduce(loading, {
            type: 'loaded',
            id: 'dir',
            nodes: [node('a')],
            nextPage: 2,
        });

        expect(
            reduce(loaded, { type: 'loading', id: 'dir' }).children.get('dir'),
        ).toEqual({ nodes: [node('a')], nextPage: 2, status: 'loading' });
    });

    it('adds the second page without repeating anyone', () => {
        const first = reduce(emptyTree, {
            type: 'loaded',
            id: 'dir',
            nodes: [node('a'), node('b')],
            nextPage: 2,
        });
        const second = reduce(first, {
            type: 'loaded',
            id: 'dir',
            nodes: [node('b'), node('c')],
            nextPage: null,
        });

        expect(second.children.get('dir')).toEqual({
            nodes: [node('a'), node('b'), node('c')],
            nextPage: null,
            status: 'ready',
        });
        expect(first.children.get('dir')?.nodes).toHaveLength(2);
    });

    it('remembers that the reports could not be loaded, keeping what it had', () => {
        const loaded = reduce(emptyTree, {
            type: 'loaded',
            id: 'dir',
            nodes: [node('a')],
            nextPage: 2,
        });

        expect(
            reduce(loaded, { type: 'failed', id: 'dir' }).children.get('dir'),
        ).toEqual({ nodes: [node('a')], nextPage: 2, status: 'error' });
        expect(
            reduce(emptyTree, { type: 'failed', id: 'dir' }).children.get(
                'dir',
            ),
        ).toEqual({ nodes: [], nextPage: null, status: 'error' });
    });
});

describe('visibleIds and parentsOf', () => {
    const loaded = reduce(
        reduce(emptyTree, {
            type: 'loaded',
            id: 'dir',
            nodes: [node('a'), node('b')],
            nextPage: null,
        }),
        { type: 'loaded', id: 'a', nodes: [node('ana')], nextPage: null },
    );
    const roots = [node('dir'), node('sola')];

    it('lists the people on screen in reading order, without the reports of a closed person', () => {
        expect(visibleIds(roots, loaded)).toEqual(['dir', 'sola']);

        const open = reduce(loaded, { type: 'toggle', id: 'dir' });

        expect(visibleIds(roots, open)).toEqual(['dir', 'a', 'b', 'sola']);
        expect(
            visibleIds(roots, reduce(open, { type: 'toggle', id: 'a' })),
        ).toEqual(['dir', 'a', 'ana', 'b', 'sola']);
        // Open before its reports arrive: nothing below it yet.
        expect(
            visibleIds(roots, reduce(emptyTree, { type: 'toggle', id: 'dir' })),
        ).toEqual(['dir', 'sola']);
    });

    it('tells who each loaded person reports to', () => {
        expect([...parentsOf(roots, loaded)]).toEqual([
            ['dir', null],
            ['a', 'dir'],
            ['ana', 'a'],
            ['b', 'dir'],
            ['sola', null],
        ]);
    });
});

describe('focusedTree', () => {
    it('opens the chain as a path of one report per level, down to the reports of the person', () => {
        const chain = [
            node('dir', { direct_reports: 2 }),
            node('a', { direct_reports: 1 }),
            node('ana', { direct_reports: 3 }),
        ];
        const tree = focusedTree(chain, page([node('x'), node('y')], 1, 2));

        expect([...tree.expanded]).toEqual(['dir', 'a', 'ana']);
        expect([...tree.children]).toEqual([
            // The rest of the reports of whoever has more wait for «Ver más», from their first page.
            ['dir', { nodes: [chain[1]], nextPage: 1, status: 'ready' }],
            ['a', { nodes: [chain[2]], nextPage: null, status: 'ready' }],
            [
                'ana',
                { nodes: [node('x'), node('y')], nextPage: 2, status: 'ready' },
            ],
        ]);
    });

    it('counts apart a manager in the chain who left: none of the active reports is on screen yet', () => {
        const chain = [
            node('dir', { direct_reports: 1 }),
            node('left', { active: false }),
            node('ana'),
        ];
        const tree = focusedTree(chain, page([]));

        expect(tree.children.get('dir')?.nextPage).toBe(1);
        expect(tree.children.get('left')?.nextPage).toBeNull();
        expect(tree.children.get('ana')).toEqual({
            nodes: [],
            nextPage: null,
            status: 'ready',
        });
        // Nobody below the person: nothing to open.
        expect([...tree.expanded]).toEqual(['dir', 'left']);
    });

    it('is empty without a focus', () => {
        expect(focusedTree([], null)).toEqual(emptyTree);
    });
});

describe('reportsPage', () => {
    it('reads a page of reports as the server sends it', () => {
        expect(
            reportsPage({ data: [node('a')], current_page: 1, last_page: 3 }),
        ).toEqual({ nodes: [node('a')], nextPage: 2 });
        expect(
            reportsPage({ data: [node('a')], current_page: 3, last_page: 3 }),
        ).toEqual({ nodes: [node('a')], nextPage: null });
    });

    it('keeps only what has the shape of a person', () => {
        expect(
            reportsPage({
                data: [
                    node('a', {
                        flag: {
                            value: 'few',
                            label: 'Revisar: pocos reportes',
                        },
                    }),
                    null,
                    'ana',
                    { id: 'b' },
                    { ...node('c'), direct_reports: '2' },
                    { ...node('d'), active: 'yes' },
                    { ...node('e'), flag: 'few' },
                    { ...node('f'), flag: { value: 'other', label: 'x' } },
                    { ...node('g'), flag: { value: 'many' } },
                ],
                current_page: 1,
                last_page: 1,
            })?.nodes.map((found) => found.id),
        ).toEqual(['a']);
    });

    it('reads nothing from an answer that is not a page', () => {
        expect(reportsPage(null)).toBeNull();
        expect(reportsPage([])).toBeNull();
        expect(reportsPage({ data: 'x' })).toBeNull();
        expect(
            reportsPage({ data: [], current_page: '1', last_page: 1 }),
        ).toBeNull();
        expect(
            reportsPage({ data: [], current_page: 1, last_page: null }),
        ).toBeNull();
    });
});
