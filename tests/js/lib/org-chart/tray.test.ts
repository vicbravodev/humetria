import { describe, expect, it } from 'vite-plus/test';

import { asOrgNode, trayUrl } from '@/lib/org-chart/tray';

describe('asOrgNode', () => {
    it('turns someone of the tray into a card that leads nobody', () => {
        expect(
            asOrgNode({
                id: 'a',
                name: 'Valeria Montes',
                job_title: 'Analista',
                area: 'Operaciones',
                work_center: 'Oficina',
            }),
        ).toEqual({
            id: 'a',
            name: 'Valeria Montes',
            job_title: 'Analista',
            area: 'Operaciones',
            work_center: 'Oficina',
            active: true,
            direct_reports: 0,
            total_reports: 0,
            flag: null,
            matches: true,
            manager_inactive: false,
            absent_today: false,
        });
    });
});

describe('trayUrl', () => {
    it('asks for a page of the tray, with the search only when something was typed', () => {
        expect(trayUrl('/tray', '', 1)).toBe('/tray?page=1');
        expect(trayUrl('/tray', ' val ', 2)).toBe('/tray?page=2&q=val');
    });
});
