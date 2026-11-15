import { describe, expect, it } from 'vite-plus/test';

import { initialChart, reduceChart } from '@/lib/org-chart/chart-state';
import {
    managerName,
    managerOf,
    panelOf,
    teamPreview,
} from '@/lib/org-chart/panel';
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

const state = reduceChart(
    initialChart({
        roots: [node('dir', { direct_reports: 2, total_reports: 3 })],
        firstLevel: {
            dir: [
                node('gte', { direct_reports: 1, total_reports: 1 }),
                node('sofia'),
            ],
        },
        chain: [],
        focusChildren: null,
    }),
    { type: 'loaded', id: 'gte', nodes: [node('ana')], nextPage: null },
);

describe('panelOf', () => {
    it('gives a person with their manager and the reports loaded', () => {
        const panel = panelOf(state, 'gte');

        expect(panel?.node.id).toBe('gte');
        expect(panel?.manager?.id).toBe('dir');
        expect(panel?.reports.map(({ id }) => id)).toEqual(['ana']);
        expect(panel?.more).toBe(false);
    });

    it('has no manager for a person at the top, and says when more of their team is still to load', () => {
        const top = panelOf(state, 'dir');

        expect(top?.manager).toBeNull();
        expect(top?.reports.map(({ id }) => id)).toEqual(['gte', 'sofia']);
        expect(
            panelOf(
                reduceChart(state, {
                    type: 'loaded',
                    id: 'sofia',
                    nodes: [],
                    nextPage: 2,
                }),
                'sofia',
            )?.more,
        ).toBe(true);
    });

    it('knows nobody that is not on the chart, and a person whose reports have not arrived', () => {
        expect(panelOf(state, 'nobody')).toBeNull();
        expect(panelOf(state, 'sofia')?.reports).toEqual([]);
    });
});

describe('teamPreview', () => {
    const people = ['a', 'b', 'c', 'd', 'e', 'f', 'g'].map((id) => node(id));

    it('shows the first five and says how many there are in all', () => {
        expect(teamPreview(people, 17, false)).toEqual({
            shown: people.slice(0, 5),
            seeAll: 'Ver los 17',
        });
    });

    it('shows everything loaded once asked', () => {
        expect(teamPreview(people, 17, true)).toEqual({
            shown: people,
            seeAll: null,
        });
    });

    it('does not offer more when the team is small', () => {
        expect(teamPreview(people.slice(0, 3), 3, false)).toEqual({
            shown: people.slice(0, 3),
            seeAll: null,
        });
    });
});

describe('managerName', () => {
    it('keeps the name of a person «Nombre · Puesto»', () => {
        expect(managerName('Ander Noriega · Analista')).toBe('Ander Noriega');
        expect(managerName('Ander Noriega')).toBe('Ander Noriega');
    });
});

describe('managerOf', () => {
    it('stands for a person by id and name, and for nobody on the empty option', () => {
        expect(managerOf({ value: 'a', label: 'Ana · Operadora' })).toEqual({
            id: 'a',
            name: 'Ana',
        });
        expect(managerOf({ value: '', label: 'Sin jefe directo' })).toBeNull();
    });
});
