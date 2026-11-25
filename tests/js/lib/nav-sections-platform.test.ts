import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';

const titles = (
    section: string,
    options: { isStaff: boolean; isSuperadmin?: boolean },
) =>
    navSections({ tenant: null, ...options })
        .find(({ key }) => key === section)
        ?.items.map(({ title }) => title);

describe('navSections for the console', () => {
    it('gives the superadmin the customers, the sales desk and the platform, each in its own group', () => {
        const staff = { isStaff: true, isSuperadmin: true };

        expect(titles('main', staff)).toEqual([
            'Resumen',
            'Empresas',
            'Cuentas',
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

        expect(titles('main', staff)).toEqual(['Empresas', 'Cuentas']);
        expect(titles('sales', staff)).toEqual(['Solicitudes de demo']);
        expect(titles('platform', staff)).toBeUndefined();
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
