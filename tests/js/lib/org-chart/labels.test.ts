import { describe, expect, it } from 'vite-plus/test';

import {
    orgChartQuery,
    placeText,
    teamPersonText,
    teamText,
} from '@/lib/org-chart/labels';

describe('org chart labels', () => {
    it('says the job title and the area of someone of the team, with what is known', () => {
        expect(teamPersonText({ job_title: 'Vendedor', area: 'Ventas' })).toBe(
            'Vendedor · Ventas',
        );
        expect(teamPersonText({ job_title: '', area: 'Ventas' })).toBe(
            'Ventas',
        );
        expect(teamPersonText({ job_title: '', area: '' })).toBe('');
    });

    it('says the size of a team', () => {
        expect(teamText({ direct_reports: 1, total_reports: 1 })).toBe(
            '1 directo · 1 en total',
        );
        expect(teamText({ direct_reports: 3, total_reports: 21 })).toBe(
            '3 directos · 21 en total',
        );
    });

    it('says where a person works, with what is known', () => {
        expect(placeText({ area: 'Ventas', work_center: 'Planta' })).toBe(
            'Ventas · Planta',
        );
        expect(placeText({ area: '', work_center: 'Planta' })).toBe('Planta');
        expect(placeText({ area: '', work_center: '' })).toBe('');
    });

    it('builds the query of the page with only what narrows it', () => {
        const none = { work_center: null, area: null };

        expect(orgChartQuery(none)).toEqual({});
        expect(
            orgChartQuery(
                {
                    work_center: { value: 'c1', label: 'Planta' },
                    area: { value: 'a1', label: 'Ventas' },
                },
                'p1',
            ),
        ).toEqual({ work_center: 'c1', area: 'a1', focus: 'p1' });
        expect(orgChartQuery(none, 'p1')).toEqual({ focus: 'p1' });
    });
});
