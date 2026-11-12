import { describe, expect, it } from 'vite-plus/test';

import { asOrgNode, trayPage, trayUrl } from '@/lib/org-chart/tray';

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

describe('trayPage', () => {
    const person = {
        id: 'a',
        name: 'Valeria',
        job_title: '',
        area: '',
        work_center: '',
    };

    it('reads a page as the server sends it, with the page that follows', () => {
        expect(
            trayPage({
                data: [person],
                total: 30,
                current_page: 1,
                last_page: 2,
            }),
        ).toEqual({
            people: [person],
            total: 30,
            nextPage: 2,
        });
        expect(
            trayPage({ data: [], total: 0, current_page: 1, last_page: 1 })
                ?.nextPage,
        ).toBeNull();
    });

    it('keeps only what has the shape of a person, and reads nothing from what is no page', () => {
        expect(
            trayPage({
                data: [person, { id: 'b' }, null],
                total: 2,
                current_page: 1,
                last_page: 1,
            })?.people,
        ).toEqual([person]);
        expect(trayPage(null)).toBeNull();
        expect(trayPage({ data: [] })).toBeNull();
        expect(
            trayPage({ data: [], total: 'x', current_page: 1, last_page: 1 }),
        ).toBeNull();
        expect(
            trayPage({ data: [], total: 0, current_page: '1', last_page: 1 }),
        ).toBeNull();
        expect(
            trayPage({ data: [], total: 0, current_page: 1, last_page: '1' }),
        ).toBeNull();
        expect(
            trayPage({ data: 'x', total: 0, current_page: 1, last_page: 1 }),
        ).toBeNull();
    });
});
