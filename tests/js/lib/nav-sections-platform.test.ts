import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';
import { can } from '../support/tenant-can';

const titles = (
    section: string,
    options: { isStaff: boolean; isSuperadmin?: boolean },
) =>
    navSections({ tenant: null, ...options })
        .find(({ key }) => key === section)
        ?.items.map(({ title }) => title);

describe('navSections for the console', () => {
    it('gives the superadmin the overview, the customers, the sales desk and the platform, each in its own group', () => {
        const staff = { isStaff: true, isSuperadmin: true };

        expect(titles('main', staff)).toEqual(['Resumen']);
        expect(titles('clients', staff)).toEqual([
            'Empresas',
            'Cuentas y cobranza',
        ]);
        expect(titles('sales', staff)).toEqual([
            'Solicitudes de demo',
            'Sala de demo',
        ]);
        expect(titles('platform', staff)).toEqual([
            'Usuarios',
            'Bitácora',
            'Colas',
        ]);
    });

    it('gives support the customers and the demo requests, and nothing of the platform', () => {
        const staff = { isStaff: true };

        expect(titles('main', staff)).toBeUndefined();
        expect(titles('clients', staff)).toEqual([
            'Empresas',
            'Cuentas y cobranza',
        ]);
        expect(titles('sales', staff)).toEqual(['Solicitudes de demo']);
        expect(titles('platform', staff)).toBeUndefined();
    });

    it('keeps the whole console in one group for staff inside a company', () => {
        const inside = navSections({
            tenant: {
                slug: 'acme',
                role: 'owner',
                can: can(true, true),
            },
            isStaff: true,
            isSuperadmin: true,
        }).find((section) => section.key === 'console');

        expect(inside?.items.map(({ title }) => title)).toEqual([
            'Resumen',
            'Empresas',
            'Cuentas y cobranza',
        ]);
    });

    it('links the queue dashboard as a plain address, since it is not a page of the panel', () => {
        const queues = navSections({
            tenant: null,
            isStaff: true,
            isSuperadmin: true,
        })
            .find((section) => section.key === 'platform')
            ?.items.find((item) => item.title === 'Colas');

        expect(queues?.href).toBe('/horizon');
        expect(queues?.external).toBe(true);
    });
});
