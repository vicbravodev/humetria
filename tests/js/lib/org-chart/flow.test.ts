import { describe, expect, it } from 'vite-plus/test';

import type { ViewEdge, ViewNode } from '@/lib/org-chart/chart-view';
import { applyDrag, openingRequest, toFlow } from '@/lib/org-chart/flow';
import type { OrgNode } from '@/types';

const person: OrgNode = {
    id: 'a',
    name: 'Ana',
    job_title: '',
    area: '',
    work_center: '',
    active: true,
    direct_reports: 1,
    total_reports: 1,
    flag: null,
    matches: true,
    manager_inactive: false,
    absent_today: false,
};

const nodes: ViewNode[] = [
    {
        id: 'company',
        width: 216,
        height: 72,
        stack: null,
        data: { kind: 'company', name: 'Acme', people: 3 },
    },
    {
        id: 'a',
        width: 216,
        height: 72,
        stack: null,
        data: {
            kind: 'person',
            node: person,
            open: true,
            tone: 'normal',
            attention: false,
            status: 'ready',
        },
    },
    {
        id: 'more-a',
        width: 216,
        height: 44,
        stack: null,
        data: { kind: 'more', parentId: 'a', left: 3, status: 'ready' },
    },
];
const edges: ViewEdge[] = [
    {
        id: 'company>a',
        source: 'company',
        target: 'a',
        highlighted: true,
        stacked: false,
    },
    {
        id: 'a>more-a',
        source: 'a',
        target: 'more-a',
        highlighted: false,
        stacked: false,
    },
];
const positions = new Map([
    ['company', { x: 10, y: 0 }],
    ['a', { x: 0, y: 144 }],
    ['more-a', { x: 0, y: 288 }],
]);

describe('toFlow', () => {
    const flow = toFlow(
        { nodes, edges },
        { positions, dragging: null, drop: null },
    );

    it('puts each card where the layout says, as the kind of node it is', () => {
        expect(
            flow.nodes.map(({ id, type, position }) => [id, type, position.y]),
        ).toEqual([
            ['company', 'company', 0],
            ['a', 'person', 144],
            ['more-a', 'more', 288],
        ]);
    });

    it('lets only people be dragged, and takes them out of the tab order: their button is the stop', () => {
        expect(flow.nodes.map((node) => node.draggable)).toEqual([
            false,
            true,
            false,
        ]);
        expect(
            flow.nodes.every(
                (node) => node.focusable === false && node.selectable === false,
            ),
        ).toBe(true);
    });

    it('draws the edges as elbows, lit on the way of a search', () => {
        expect(
            flow.edges.map(({ type, style }) => [
                type,
                style?.stroke,
                style?.strokeWidth,
            ]),
        ).toEqual([
            ['smoothstep', 'var(--primary)', 2],
            ['smoothstep', 'var(--input)', 1.5],
        ]);
    });

    it('draws the card being dragged where the pointer has it, and says what a drop on a card does', () => {
        const dragged = toFlow(
            { nodes, edges },
            {
                positions,
                dragging: { id: 'a', x: 5, y: 6 },
                drop: { id: 'a', tone: 'valid' },
            },
        );
        const first = dragged.nodes[1];

        expect(first?.position).toEqual({ x: 5, y: 6 });
        expect(first?.type === 'person' ? first.data.drop : null).toBe('valid');
        expect(
            flow.nodes[1]?.type === 'person' ? flow.nodes[1].data.drop : 'x',
        ).toBeNull();
    });

    it('places at the origin a card the layout does not know', () => {
        expect(
            toFlow(
                { nodes, edges },
                { positions: new Map(), dragging: null, drop: null },
            ).nodes[1]?.position,
        ).toEqual({ x: 0, y: 0 });
    });
});

describe('toFlow, columns', () => {
    const flow = toFlow(
        { nodes, edges },
        { positions, dragging: null, drop: null },
    );

    it('sends a column from the left of its manager into the left of each card', () => {
        const kid: ViewNode = {
            id: 'k',
            width: 216,
            height: 72,
            stack: 'a',
            data: {
                kind: 'person',
                node: person,
                open: undefined,
                tone: 'normal',
                attention: false,
                status: undefined,
            },
        };
        const column = toFlow(
            {
                nodes: [kid],
                edges: [
                    {
                        id: 'a>k',
                        source: 'a',
                        target: 'k',
                        highlighted: false,
                        stacked: true,
                    },
                ],
            },
            { positions, dragging: null, drop: null },
        );
        const first = column.nodes[0];

        expect(column.edges[0]?.sourceHandle).toBe('stack');
        expect(first?.type === 'person' ? first.data.stacked : null).toBe(true);
        expect(flow.edges[0]?.sourceHandle).toBeNull();
        expect(
            flow.nodes.map((node) =>
                node.type === 'company' ? null : node.data.stacked,
            ),
        ).toEqual([null, false, false]);
    });
});

describe('applyDrag', () => {
    const base = toFlow(
        { nodes, edges },
        { positions, dragging: null, drop: null },
    ).nodes;

    it('is the same cards when nothing is being dragged', () => {
        expect(applyDrag(base, { dragging: null, drop: null })).toBe(base);
    });

    it('moves only the card dragged and marks only the one under it', () => {
        const moved = applyDrag(base, {
            dragging: { id: 'a', x: 7, y: 8 },
            drop: { id: 'a', tone: 'invalid' },
        });
        const other = applyDrag(base, {
            dragging: { id: 'company', x: 1, y: 2 },
            drop: { id: 'a', tone: 'valid' },
        });

        expect(moved[1]?.position).toEqual({ x: 7, y: 8 });
        expect(moved[0]).toBe(base[0]);
        expect(moved[2]).toBe(base[2]);
        expect(other[0]?.position).toEqual({ x: 1, y: 2 });
        expect(other[1]?.type === 'person' ? other[1].data.drop : null).toBe(
            'valid',
        );
        expect(other[2]).toBe(base[2]);
    });

    it('does not mark as a place to drop a card that is not a person', () => {
        const marked = applyDrag(base, {
            dragging: null,
            drop: { id: 'company', tone: 'valid' },
        });

        expect(marked[0]).toBe(base[0]);
    });
});

describe('openingRequest', () => {
    const head = (id: string, direct_reports = 1): OrgNode => ({
        ...person,
        id,
        direct_reports,
    });
    const child = head('kid', 0);

    it('brings the company card into the opening view when several heads hang from it', () => {
        expect(
            openingRequest({
                roots: [head('a'), head('b'), head('loner', 0)],
                firstLevel: { a: [child] },
                chain: [],
                focusChildren: null,
            }),
        ).toEqual({
            ids: null,
            readable: ['company', 'a', 'kid', 'b', 'loner'],
        });
    });

    it('has no company card to bring when there is a single head', () => {
        expect(
            openingRequest({
                roots: [head('a')],
                firstLevel: {},
                chain: [],
                focusChildren: null,
            }),
        ).toEqual({ ids: null, readable: ['a'] });
    });

    it('goes to the way down to the person a search found', () => {
        expect(
            openingRequest({
                roots: [head('a')],
                firstLevel: {},
                chain: [head('a'), child],
                focusChildren: null,
            }),
        ).toEqual({ ids: ['a', 'kid'] });
    });
});
