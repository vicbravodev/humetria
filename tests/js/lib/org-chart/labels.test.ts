import { describe, expect, it } from 'vite-plus/test';

import {
    moreText,
    orgChartHeadline,
    orgChartQuery,
    placeText,
    teamText,
} from '@/lib/org-chart/labels';

describe('org chart labels', () => {
    it('says how many people the company has and how many have a manager', () => {
        expect(orgChartHeadline(0, 0)).toBe('Nadie activo por ahora.');
        expect(orgChartHeadline(1, 0)).toBe(
            '1 persona activa, ninguna con jefe directo.',
        );
        expect(orgChartHeadline(120, 87)).toBe(
            '120 personas activas, 87 con jefe directo.',
        );
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

    it('counts the reports still to load, unless a filter hides some', () => {
        expect(moreText(60, 50, false)).toBe('Ver 10 más');
        expect(moreText(60, 50, true)).toBe('Ver más');
        expect(moreText(2, 2, false)).toBe('Ver más');
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
