import { describe, expect, it } from 'vite-plus/test';

import { activeChild, complianceNavItems } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

const acme = (manage: boolean, view = true) => ({
    slug: 'acme',
    role: manage ? ('hr' as const) : ('viewer' as const),
    can: {
        ...can(manage, manage),
        view_vacations: view,
        manage_vacations: manage,
    },
});

const vacations = (manage: boolean, view = true) =>
    complianceNavItems(acme(manage, view)).find(
        (item) => item.title === 'Vacaciones',
    );

describe('the vacations in the sidebar', () => {
    it('offers them after the registro de jornada, with their five pages, to whoever may view them', () => {
        const tenant = acme(false);
        const items = complianceNavItems({
            ...tenant,
            can: { ...tenant.can, view_attendance: true },
        });
        const entry = items.find((item) => item.title === 'Vacaciones');

        expect(items.map((item) => item.title).slice(-2)).toEqual([
            'Asistencia',
            'Vacaciones',
        ]);
        expect(toUrl(entry?.href ?? '')).toBe('/admin/acme/vacations');
        expect(
            (entry?.children ?? []).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([
            ['Resumen', '/admin/acme/vacations'],
            ['Solicitudes', '/admin/acme/vacations/requests'],
            ['Calendario', '/admin/acme/vacations/calendar'],
            ['Saldos', '/admin/acme/vacations/balances'],
            ['Políticas', '/admin/acme/vacations/policies'],
        ]);
    });

    it('keeps the settings and the import for whoever manages them', () => {
        expect(
            (vacations(true)?.children ?? []).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([
            ['Resumen', '/admin/acme/vacations'],
            ['Solicitudes', '/admin/acme/vacations/requests'],
            ['Calendario', '/admin/acme/vacations/calendar'],
            ['Saldos', '/admin/acme/vacations/balances'],
            ['Políticas', '/admin/acme/vacations/policies'],
            ['Configuración', '/admin/acme/vacations/settings'],
            ['Importar', '/admin/acme/vacations/import'],
        ]);
    });

    it('is not there for a role that may not view them', () => {
        expect(vacations(false, false)).toBeUndefined();
    });

    it('lights each page by its own address', () => {
        const children = vacations(true)?.children ?? [];

        expect(activeChild(children, '/admin/acme/vacations')?.title).toBe(
            'Resumen',
        );
        expect(
            activeChild(children, '/admin/acme/vacations/requests')?.title,
        ).toBe('Solicitudes');
        expect(
            activeChild(children, '/admin/acme/vacations/import')?.title,
        ).toBe('Importar');
    });
});
