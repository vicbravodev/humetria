import { describe, expect, it } from 'vite-plus/test';

import {
    indicatorStats,
    moreText,
    orgChartHeadline,
    orgChartQuery,
    placeText,
    teamPersonText,
    teamText,
} from '@/lib/org-chart/labels';

describe('org chart labels', () => {
    it('tells the three indicators, the median with one decimal', () => {
        expect(
            indicatorStats({
                levels: 4,
                median_direct_reports: 6.5,
                managers: 12,
                to_review: 3,
            }),
        ).toEqual([
            { label: 'Niveles', value: '4' },
            { label: 'Mediana de reportes directos', value: '6.5' },
            {
                label: 'Jefes por revisar',
                value: '3',
                hint: 'Con 1–2 o más de 15 reportes directos. Es una señal, no una regla.',
            },
        ]);
        expect(
            indicatorStats({
                levels: 3,
                median_direct_reports: 2,
                managers: 3,
                to_review: 0,
            })[1]?.value,
        ).toBe('2.0');
    });

    it('leaves a dash for the median where nobody leads anyone', () => {
        expect(
            indicatorStats({
                levels: 1,
                median_direct_reports: null,
                managers: 0,
                to_review: 0,
            })[1]?.value,
        ).toBe('—');
    });

    it('says how many people the company has and how many have a manager', () => {
        expect(orgChartHeadline(0, 0)).toBe('Nadie activo por ahora.');
        expect(orgChartHeadline(1, 0)).toBe(
            '1 persona activa, ninguna con jefe directo.',
        );
        expect(orgChartHeadline(120, 87)).toBe(
            '120 personas activas, 87 con jefe directo.',
        );
    });

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
