import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';

describe('navSections for the platform', () => {
    it('gives the platform users and the audit trail to the superadmin only', () => {
        const platform = (isStaff: boolean, isSuperadmin?: boolean) =>
            navSections({ tenant: null, isStaff, isSuperadmin })
                .find((section) => section.key === 'platform')
                ?.items.map((item) => item.title);

        expect(platform(true, true)).toEqual([
            'Empresas',
            'Cuentas',
            'Solicitudes de demo',
            'Usuarios',
            'Bitácora',
        ]);
        expect(platform(true)).toEqual([
            'Empresas',
            'Cuentas',
            'Solicitudes de demo',
        ]);
    });
});
