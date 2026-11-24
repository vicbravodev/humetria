import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { mobileTabs } from '@/lib/mobile-tabs';
import { listeningNavItems, operationsNavItems } from '@/lib/tool-navigation';
import { templateCan } from '../support/tenant-can';
import type { CompanyRoleKey } from '@/types';

const acme = (
    role: CompanyRoleKey,
    template: string,
    extra: string[] = [],
) => ({
    slug: 'acme',
    role,
    can: templateCan(template, extra),
});

const titles = (items: { title: string }[]) => items.map((item) => item.title);

/** Every place the menu is drawn: the sidebar's four sections and the phone's tabs. */
const menu = (tenant: ReturnType<typeof acme>) => ({
    main: titles(mainNavItems(tenant, false)),
    compliance: titles(complianceNavItems(tenant)),
    listening: titles(listeningNavItems(tenant)),
    operations: titles(operationsNavItems(tenant)),
    tabs: mobileTabs(tenant, false).map((tab) => tab.title),
});

describe('the menu of each template', () => {
    it('stays what it was for the five templates', () => {
        expect(menu(acme('owner', 'owner'))).toEqual({
            main: [
                'Panel',
                'Centros de trabajo',
                'Empleados',
                'Accesos',
                'Usuarios',
                'Roles',
                'Plan y uso',
                'Privacidad',
            ],
            compliance: ['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia'],
            listening: ['Quejas', 'Clima', 'Pulso'],
            operations: ['Vacaciones', 'Organigrama', 'Compensación'],
            tabs: ['Panel', 'Normas', 'Operación', 'Quejas', 'Más'],
        });
        expect(menu(acme('hr', 'hr'))).toEqual({
            main: ['Panel', 'Centros de trabajo', 'Empleados', 'Accesos'],
            compliance: ['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia'],
            listening: ['Quejas', 'Clima', 'Pulso'],
            operations: ['Vacaciones', 'Organigrama', 'Compensación'],
            tabs: ['Panel', 'Normas', 'Operación', 'Quejas', 'Más'],
        });
        expect(menu(acme('viewer', 'viewer'))).toEqual({
            main: ['Panel', 'Centros de trabajo', 'Accesos'],
            compliance: ['NOM-035', 'NOM-037', 'Ley Silla', 'Asistencia'],
            listening: ['Clima', 'Pulso'],
            operations: ['Vacaciones', 'Organigrama'],
            tabs: ['Panel', 'Normas', 'Operación', 'Más'],
        });
        expect(menu(acme('committee', 'committee'))).toEqual({
            main: [],
            compliance: ['NOM-037', 'Ley Silla'],
            listening: ['Quejas'],
            operations: [],
            tabs: ['Normas', 'Quejas', 'Más'],
        });
        expect(menu(acme('complaints', 'complaints'))).toEqual({
            main: ['Casos', 'Cómo se atiende'],
            compliance: [],
            listening: [],
            operations: [],
            tabs: ['Quejas', 'Más'],
        });
    });
});

describe('the menu of a template given more permissions', () => {
    it('shows the handler of complaints given attendance its attendance besides its cases', () => {
        const tenant = acme('complaints', 'complaints', ['view_attendance']);

        expect(titles(complianceNavItems(tenant))).toEqual(['Asistencia']);
        expect(titles(listeningNavItems(tenant))).toEqual(['Quejas']);
    });

    it('shows the Comisión given the centers and the access of workers their pages, and the panel the centers complete', () => {
        const tenant = acme('committee', 'committee', [
            'view_work_centers',
            'view_worker_access',
        ]);

        expect(titles(mainNavItems(tenant, false))).toEqual([
            'Panel',
            'Centros de trabajo',
            'Accesos',
        ]);
    });

    it('gives a role of the company that only handles complaints the menu of the outside handler', () => {
        expect(menu(acme('custom_9f2', 'complaints'))).toEqual(
            menu(acme('complaints', 'complaints')),
        );
    });

    it('gives a role of the company made like the Comisión the menu of the Comisión', () => {
        expect(menu(acme('custom_9f2', 'committee'))).toEqual(
            menu(acme('committee', 'committee')),
        );
    });
});
