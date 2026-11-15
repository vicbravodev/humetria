import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';

describe('navSections for the platform', () => {
    it('gives the platform users and the audit trail to the superadmin only', () => {
        const platform = (isStaff: boolean, isSuperadmin?: boolean) =>
            navSections({ tenant: null, isStaff, isSuperadmin })
                .find((section) => section.key === 'platform')
                ?.items.map((item) => item.title);

        expect(platform(true, true)).toEqual([
            'Resumen',
            'Empresas',
            'Cuentas',
            'Solicitudes de demo',
            'Usuarios',
            'Bitácora',
            'Colas',
        ]);
        expect(platform(true)).toEqual([
            'Empresas',
            'Cuentas',
            'Solicitudes de demo',
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
