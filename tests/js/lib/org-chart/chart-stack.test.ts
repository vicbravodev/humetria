import { describe, expect, it } from 'vite-plus/test';

import { initialChart } from '@/lib/org-chart/chart-state';
import { chartGraph, moreId } from '@/lib/org-chart/chart-view';
import type { ViewOptions } from '@/lib/org-chart/chart-view';
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

const options = (extra: Partial<ViewOptions> = {}): ViewOptions => ({
    company: 'Acme',
    people: 212,
    selected: null,
    highlight: null,
    ...extra,
});

describe('chartGraph, teams of people who lead nobody', () => {
    const team = (
        extra: Partial<OrgNode> = {},
        kids = [node('k1'), node('k2')],
    ) =>
        initialChart({
            roots: [
                node('boss', {
                    direct_reports: kids.length,
                    total_reports: kids.length,
                    ...extra,
                }),
            ],
            firstLevel: { boss: kids },
            chain: [],
            focusChildren: null,
        });

    it('stacks them under their manager: two or more people who lead nobody hang in a column', () => {
        const graph = chartGraph(team(), options());

        expect(graph.nodes.map(({ id, stack }) => [id, stack])).toEqual([
            ['boss', null],
            ['k1', 'boss'],
            ['k2', 'boss'],
        ]);
        expect(graph.edges.map(({ id, stacked }) => [id, stacked])).toEqual([
            ['boss>k1', true],
            ['boss>k2', true],
        ]);
    });

    it('keeps one report, or a team where somebody leads people, side by side', () => {
        expect(
            chartGraph(team({}, [node('k1')]), options()).nodes.map(
                ({ stack }) => stack,
            ),
        ).toEqual([null, null]);
        expect(
            chartGraph(
                team({}, [node('k1', { direct_reports: 1 }), node('k2')]),
                options(),
            ).nodes.map(({ stack }) => stack),
        ).toEqual([null, null, null]);
    });

    it('ends the stack with the card that asks for the rest', () => {
        const state = initialChart({
            roots: [node('boss', { direct_reports: 60 })],
            firstLevel: {
                boss: Array.from({ length: 50 }, (_, i) => node(`p${i}`)),
            },
            chain: [],
            focusChildren: null,
        });
        const last = chartGraph(state, options()).nodes.at(-1);

        expect(last?.id).toBe(moreId('boss'));
        expect(last?.stack).toBe('boss');
    });
});
