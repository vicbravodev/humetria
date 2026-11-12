import { describe, expect, it } from 'vite-plus/test';

import { initialChart, reduceChart } from '@/lib/org-chart/chart-state';
import { crumbsOf, headsOf, startAt } from '@/lib/org-chart/mobile';
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

const lead = (id: string) => node(id, { direct_reports: 1, total_reports: 2 });

const state = reduceChart(
    initialChart({
        roots: [lead('dir'), node('lola')],
        firstLevel: { dir: [lead('gte')] },
        chain: [],
        focusChildren: null,
    }),
    { type: 'loaded', id: 'gte', nodes: [node('ana')], nextPage: null },
);

describe('headsOf', () => {
    it('lists whoever has nobody above and leads people, leaving out whoever leads nobody', () => {
        expect(headsOf(state).map(({ id }) => id)).toEqual(['dir']);
    });
});

describe('startAt', () => {
    it('starts on the only head, so there is nothing to choose', () => {
        expect(startAt(state, null)).toBe('dir');
    });

    it('starts on the focused person when a search found one', () => {
        expect(startAt(state, 'ana')).toBe('ana');
    });

    it('starts at the company when there are several heads', () => {
        const many = initialChart({
            roots: [lead('a'), lead('b')],
            firstLevel: {},
            chain: [],
            focusChildren: null,
        });

        expect(startAt(many, null)).toBeNull();
    });
});

describe('crumbsOf', () => {
    it('goes from the company down to the person, the person last', () => {
        expect(crumbsOf(state, 'ana', 'Acme')).toEqual([
            { id: null, label: 'Acme' },
            { id: 'dir', label: 'dir' },
            { id: 'gte', label: 'gte' },
            { id: 'ana', label: 'ana' },
        ]);
    });

    it('is the company alone at the top', () => {
        expect(crumbsOf(state, null, 'Acme')).toEqual([
            { id: null, label: 'Acme' },
        ]);
    });

    it('does not name a person that is not on the chart', () => {
        expect(crumbsOf(state, 'nobody', 'Acme')).toEqual([
            { id: null, label: 'Acme' },
        ]);
    });
});
