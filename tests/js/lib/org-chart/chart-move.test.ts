import { describe, expect, it } from 'vite-plus/test';

import {
    initialChart,
    parentsOf,
    reduceChart,
} from '@/lib/org-chart/chart-state';
import type { ChartState } from '@/lib/org-chart/chart-state';
import { dropMessage, dropState, targetAt } from '@/lib/org-chart/drag';
import { moveInChart } from '@/lib/org-chart/chart-move';
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

// dir leads gte (who leads ana and beto) and sofia; lola is another root with nobody.
const dir = node('dir', { direct_reports: 2, total_reports: 4 });
const gte = node('gte', { direct_reports: 2, total_reports: 2 });

function chart(): ChartState {
    const state = initialChart({
        roots: [dir, node('lola')],
        firstLevel: { dir: [gte, node('sofia')] },
        chain: [],
        focusChildren: null,
    });

    return reduceChart(state, {
        type: 'loaded',
        id: 'gte',
        nodes: [node('ana'), node('beto')],
        nextPage: null,
    });
}

const counts = (state: ChartState, id: string) => [
    state.people.get(id)?.direct_reports,
    state.people.get(id)?.total_reports,
];

describe('dropState', () => {
    it('lets a person be dropped on a manager who is not them, below them nor already theirs', () => {
        expect(dropState(chart(), 'ana', 'sofia')).toBe('valid');
        expect(dropState(chart(), 'gte', 'lola')).toBe('valid');
    });

    it('refuses the person themselves, whoever reports to them at any level, and their current manager', () => {
        expect(dropState(chart(), 'gte', 'gte')).toBe('self');
        expect(dropState(chart(), 'dir', 'ana')).toBe('descendant');
        expect(dropState(chart(), 'dir', 'gte')).toBe('descendant');
        expect(dropState(chart(), 'ana', 'gte')).toBe('current');
    });

    it('treats a person who is not in the chart as someone with nobody above and nobody below', () => {
        expect(dropState(chart(), 'tray', 'dir')).toBe('valid');
        expect(dropState(chart(), 'tray', 'tray')).toBe('self');
    });
});

describe('moveInChart', () => {
    it('moves a person to another branch and corrects the teams of both chains', () => {
        const moved = moveInChart(chart(), { node: node('ana'), to: 'sofia' });

        expect(parentsOf(moved).get('ana')).toBe('sofia');
        expect(moved.branches.get('gte')?.ids).toEqual(['beto']);
        expect(counts(moved, 'gte')).toEqual([1, 1]);
        expect(counts(moved, 'dir')).toEqual([2, 4]);
        expect(counts(moved, 'sofia')).toEqual([1, 1]);
    });

    it('moves the team of a person with them: the totals above change by the size of it', () => {
        const moved = moveInChart(chart(), { node: gte, to: 'lola' });

        expect(counts(moved, 'dir')).toEqual([1, 1]);
        expect(counts(moved, 'lola')).toEqual([1, 3]);
        expect(moved.branches.get('lola')).toEqual({
            ids: ['gte'],
            nextPage: null,
            status: 'ready',
        });
        expect(moved.expanded.has('lola')).toBe(true);
        expect(moved.roots).toEqual(['dir', 'lola']);
    });

    it('puts the person in order by name among the reports already loaded', () => {
        const moved = moveInChart(chart(), { node: node('aaa'), to: 'gte' });
        const ordered = moveInChart(chart(), { node: node('zzz'), to: 'gte' });

        expect(moved.branches.get('gte')?.ids).toEqual(['aaa', 'ana', 'beto']);
        expect(ordered.branches.get('gte')?.ids).toEqual([
            'ana',
            'beto',
            'zzz',
        ]);
    });

    it('takes a root under a manager, and brings a person to the top when they are left with nobody', () => {
        const under = moveInChart(chart(), { node: node('lola'), to: 'sofia' });
        const top = moveInChart(chart(), { node: node('ana'), to: null });

        expect(under.roots).toEqual(['dir']);
        expect(top.roots).toEqual(['dir', 'lola', 'ana']);
        expect(counts(top, 'gte')).toEqual([1, 1]);
        expect(counts(top, 'dir')).toEqual([2, 3]);
        expect(top.people.get('ana')?.manager_inactive).toBe(false);
    });

    it('draws a person who was not in the chart, and an undo puts every count back', () => {
        const added = moveInChart(chart(), { node: node('tray'), to: 'sofia' });
        const back = moveInChart(added, { node: node('tray'), to: null });

        expect(parentsOf(added).get('tray')).toBe('sofia');
        expect(counts(added, 'dir')).toEqual([2, 5]);
        expect(counts(back, 'dir')).toEqual([2, 4]);
        expect(counts(back, 'sofia')).toEqual([0, 0]);
    });

    it('starts the branch of a manager whose reports were not loaded, asking for the rest if they lead more people', () => {
        const state = reduceChart(chart(), {
            type: 'loaded',
            id: 'gte',
            nodes: [node('ana'), node('beto')],
            nextPage: null,
        });
        const moved = moveInChart(state, { node: node('ana'), to: 'sofia' });
        const crowded = moveInChart(
            {
                ...state,
                people: new Map(state.people).set(
                    'sofia',
                    node('sofia', { direct_reports: 4, total_reports: 4 }),
                ),
            },
            { node: node('ana'), to: 'sofia' },
        );

        expect(moved.branches.get('sofia')?.nextPage).toBeNull();
        expect(crowded.branches.get('sofia')).toEqual({
            ids: ['ana'],
            nextPage: 1,
            status: 'ready',
        });
    });

    it('does nothing when the person already reports to that manager', () => {
        const state = chart();

        expect(moveInChart(state, { node: node('ana'), to: 'gte' })).toBe(
            state,
        );
    });
});

describe('targetAt', () => {
    const rects = [
        { id: 'a', x: 0, y: 0, width: 216, height: 72 },
        { id: 'b', x: 240, y: 0, width: 216, height: 72 },
    ];

    it('finds the card under the point, and nobody over the gap or outside', () => {
        expect(targetAt({ x: 100, y: 30 }, rects, 'z')).toBe('a');
        expect(targetAt({ x: 300, y: 71 }, rects, 'z')).toBe('b');
        expect(targetAt({ x: 228, y: 30 }, rects, 'z')).toBeNull();
        expect(targetAt({ x: 100, y: 80 }, rects, 'z')).toBeNull();
    });

    it('never finds the card being dragged', () => {
        expect(targetAt({ x: 100, y: 30 }, rects, 'a')).toBeNull();
    });
});

describe('dropMessage', () => {
    it('says why a drop is not possible, calmly, and nothing when it is', () => {
        expect(
            dropMessage('descendant', { dragged: 'Esther', target: 'Ander' }),
        ).toBe('No se puede: Ander reporta a Esther.');
        expect(
            dropMessage('self', { dragged: 'Esther', target: 'Esther' }),
        ).toBe('Una persona no puede ser su propio jefe.');
        expect(dropMessage('current', { dragged: 'Ana', target: 'Gte' })).toBe(
            'Gte ya es el jefe de Ana.',
        );
        expect(
            dropMessage('valid', { dragged: 'Ana', target: 'Gte' }),
        ).toBeNull();
    });
});
