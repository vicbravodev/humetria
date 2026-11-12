import { describe, expect, it } from 'vite-plus/test';

import { initialChart, reduceChart } from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';
import { chartGraph, COMPANY_ID, moreId } from '@/lib/org-chart/chart-view';
import type { ChartNodeData, ViewOptions } from '@/lib/org-chart/chart-view';
import type { OrgNode } from '@/types';

const node = (id: string, extra: Partial<OrgNode> = {}): OrgNode => ({
    id,
    name: id,
    job_title: 'Puesto',
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

const dir = node('dir', { direct_reports: 2, total_reports: 4 });
const gte = node('gte', { direct_reports: 2, total_reports: 2 });
const other = node('other', { direct_reports: 1, total_reports: 1 });

function chart(roots: OrgNode[] = [dir]): ChartState {
    return initialChart({
        roots: [...roots, node('lola')],
        firstLevel: { dir: [gte, node('sofia')], other: [node('kid')] },
        chain: [],
        focusChildren: null,
    });
}

const options = (extra: Partial<ViewOptions> = {}): ViewOptions => ({
    company: 'Acme',
    people: 212,
    selected: null,
    highlight: null,
    ...extra,
});

const ids = (state: ChartState, extra: Partial<ViewOptions> = {}) =>
    chartGraph(state, options(extra)).nodes.map((view) => view.id);

const person = (data: ChartNodeData) => {
    if (data.kind !== 'person') {
        throw new Error('not a person');
    }

    return data;
};

describe('chartGraph', () => {
    it('draws one head as the top, with whoever is open below, and leaves out whoever leads nobody', () => {
        expect(ids(chart())).toEqual(['dir', 'gte', 'sofia']);
    });

    it('hangs several heads from a company that is not a person', () => {
        const state = chart([dir, other]);
        const graph = chartGraph(state, options());

        expect(graph.nodes.map((view) => view.id)).toEqual([
            COMPANY_ID,
            'dir',
            'gte',
            'sofia',
            'other',
            'kid',
        ]);
        expect(graph.nodes[0]?.data).toEqual({
            kind: 'company',
            name: 'Acme',
            people: 212,
        });
        expect(
            graph.edges
                .filter((edge) => edge.source === COMPANY_ID)
                .map((edge) => edge.target),
        ).toEqual(['dir', 'other']);
    });

    it('draws nothing when nobody leads anybody', () => {
        const state = initialChart({
            roots: [node('a'), node('b')],
            firstLevel: {},
            chain: [],
            focusChildren: null,
        });

        expect(ids(state)).toEqual([]);
    });

    it('leaves out the reports of a closed person, and says whether one is open only when they lead people', () => {
        const closed = reduceChart(chart(), { type: 'toggle', id: 'dir' });
        const views = chartGraph(chart(), options()).nodes;

        expect(ids(closed)).toEqual(['dir']);
        expect(
            person(views[0]?.data ?? { kind: 'company', name: '', people: 0 })
                .open,
        ).toBe(true);
        expect(
            person(views[2]?.data ?? { kind: 'company', name: '', people: 0 })
                .open,
        ).toBeUndefined();
    });

    it('joins each person to their manager with an edge', () => {
        expect(
            chartGraph(chart(), options()).edges.map(
                ({ source, target }) => `${source}>${target}`,
            ),
        ).toEqual(['dir>gte', 'dir>sofia']);
    });
});

describe('chartGraph, the rest of the reports', () => {
    it('offers the rest of the reports as one more card, saying how many are left', () => {
        const state = initialChart({
            roots: [node('big', { direct_reports: 53 })],
            firstLevel: {
                big: Array.from({ length: 50 }, (_, i) => node(`p${i}`)),
            },
            chain: [],
            focusChildren: null,
        });
        const last = chartGraph(state, options()).nodes.at(-1);

        expect(last?.id).toBe(moreId('big'));
        expect(last?.data).toEqual({
            kind: 'more',
            parentId: 'big',
            left: 3,
            status: 'ready',
        });
    });

    it('does not say how many are left when a filter hides some', () => {
        const state = initialChart({
            roots: [node('big', { direct_reports: 40 })],
            firstLevel: { big: [node('p1')] },
            chain: [],
            focusChildren: null,
        });
        const stale = {
            ...state,
            branches: new Map(state.branches).set('big', {
                ids: ['p1'],
                nextPage: 2,
                status: 'ready' as const,
            }),
        };

        expect(chartGraph(stale, options()).nodes.at(-1)?.data).toMatchObject({
            left: 39,
        });
        expect(
            chartGraph(stale, options({ filtered: true })).nodes.at(-1)?.data,
        ).toMatchObject({ left: null });
    });

    it('marks a person as selected, on the path, or dimmed while a search points at someone else', () => {
        const state = chart();
        const tones = (extra: Partial<ViewOptions>) =>
            Object.fromEntries(
                chartGraph(state, options(extra)).nodes.map((view) => [
                    view.id,
                    view.data.kind === 'person' ? view.data.tone : '',
                ]),
            );

        expect(tones({})).toEqual({
            dir: 'normal',
            gte: 'normal',
            sofia: 'normal',
        });
        expect(tones({ selected: 'sofia' })).toMatchObject({
            sofia: 'selected',
            gte: 'normal',
        });
        expect(tones({ highlight: 'gte' })).toEqual({
            dir: 'path',
            gte: 'selected',
            sofia: 'dimmed',
        });
    });

    it('lights the edges of the path to the person searched', () => {
        const edges = chartGraph(chart(), options({ highlight: 'gte' })).edges;

        expect(
            edges.map(({ target, highlighted }) => [target, highlighted]),
        ).toEqual([
            ['gte', true],
            ['sofia', false],
        ]);
    });

    it('dims whoever is only on the way to someone within the filters', () => {
        const state = initialChart({
            roots: [node('dir', { direct_reports: 1, matches: false })],
            firstLevel: { dir: [node('kid')] },
            chain: [],
            focusChildren: null,
        });
        const first = chartGraph(state, options()).nodes[0]?.data;

        expect(first?.kind === 'person' ? first.tone : '').toBe('dimmed');
    });
});

describe('chartGraph, what a card says', () => {
    it('flags for attention whoever leads more than 15 people or whose manager left, and nobody else', () => {
        const many = node('many', {
            direct_reports: 17,
            flag: { value: 'many', label: 'x' },
        });
        const few = node('few', {
            direct_reports: 1,
            flag: { value: 'few', label: 'x' },
        });
        const left = node('left', {
            direct_reports: 1,
            manager_inactive: true,
        });
        const attention = (target: OrgNode) => {
            const data = chartGraph(
                initialChart({
                    roots: [target],
                    firstLevel: {},
                    chain: [],
                    focusChildren: null,
                }),
                options(),
            ).nodes[0]?.data;

            return data?.kind === 'person' && data.attention;
        };

        expect(attention(many)).toBe(true);
        expect(attention(left)).toBe(true);
        expect(attention(few)).toBe(false);
    });

    it('carries the state of the reports being asked for', () => {
        const state = reduceChart(chart(), { type: 'loading', id: 'dir' });
        const first = chartGraph(state, options()).nodes[0]?.data;

        expect(first?.kind === 'person' ? first.status : '').toBe('loading');
    });

    it('sizes every card for the layout', () => {
        const nodes = chartGraph(chart(), options()).nodes;

        expect(nodes.map(({ width, height }) => [width, height])).toEqual([
            [216, 72],
            [216, 72],
            [216, 72],
        ]);
    });
});
