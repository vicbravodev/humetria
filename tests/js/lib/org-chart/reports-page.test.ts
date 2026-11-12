import { describe, expect, it } from 'vite-plus/test';

import { reportsPage } from '@/lib/org-chart/reports-page';
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
