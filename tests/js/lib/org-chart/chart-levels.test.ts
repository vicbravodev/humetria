import { describe, expect, it } from 'vite-plus/test';

import {
    frozen,
    initialChart,
    reduceChart,
    shown,
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
const dir = node('dir', { direct_reports: 2, total_reports: 4 });
const gte = node('gte', { direct_reports: 2, total_reports: 2 });

function chart(): ChartState {
    return reduceChart(
        initialChart({
            roots: [dir, node('lola')],
            firstLevel: { dir: [gte, node('sofia')] },
            chain: [],
            focusChildren: null,
        }),
        { type: 'loaded', id: 'gte', nodes: [node('ana')], nextPage: null },
    );
}

describe('levels', () => {
    it('opens two levels when the page arrives, and none by hand with a focused person', () => {
        expect(chart().levels).toBe(2);
        expect(
            initialChart({
                roots: [dir],
                firstLevel: {},
                chain: [dir, gte],
                focusChildren: null,
            }).levels,
        ).toBeNull();
    });

    it('keeps open whoever the levels say, whatever is loaded since', () => {
        const wide = reduceChart(chart(), { type: 'levels', levels: 3 });

        expect(wide.levels).toBe(3);
        expect(sorted([...shown(wide).expanded])).toEqual(['dir', 'gte']);
        expect(sorted([...shown(chart()).expanded])).toEqual(['dir']);
    });

    it('leaves the levels when a branch is opened or closed by hand, keeping what was open', () => {
        const wide = reduceChart(chart(), { type: 'levels', levels: 3 });
        const closed = reduceChart(wide, { type: 'toggle', id: 'dir' });
        const opened = reduceChart(chart(), { type: 'toggle', id: 'gte' });

        expect(closed.levels).toBeNull();
        expect(closed.expanded.has('dir')).toBe(false);
        expect(sorted([...opened.expanded])).toEqual(['dir', 'gte']);
        expect(
            reduceChart(wide, { type: 'expand', ids: ['dir'] }).levels,
        ).toBeNull();
    });

    it('freezes the state without changing it when nothing is by levels', () => {
        const hand = reduceChart(chart(), { type: 'expand', ids: ['dir'] });

        expect(shown(hand)).toBe(hand);
        expect(frozen(hand)).toEqual(hand);
        expect(frozen(chart()).levels).toBeNull();
    });
});

describe('large teams', () => {
    const big = node('big', { direct_reports: 16, total_reports: 16 });
    const edge = node('edge', { direct_reports: 15, total_reports: 15 });
    const chartOf = (...heads: OrgNode[]): ChartState =>
        initialChart({
            roots: heads,
            firstLevel: {},
            chain: [],
            focusChildren: null,
        });

    it('does not open a team of more than 15 under «Mostrar: N niveles»: it waits behind its pill', () => {
        expect(
            shown(chartOf(big, node('x', { direct_reports: 1 }))).expanded,
        ).toEqual(new Set(['x']));
        expect(shown(chartOf(edge)).expanded).toEqual(new Set(['edge']));
    });

    it('opens it with «Expandir todo»', () => {
        const all = reduceChart(chartOf(big), {
            type: 'levels',
            levels: Infinity,
        });

        expect(shown(all).expanded).toEqual(new Set(['big']));
    });
});
