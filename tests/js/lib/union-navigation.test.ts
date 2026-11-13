import { describe, expect, it } from 'vite-plus/test';

import type { NavTenant } from '@/lib/app-navigation';
import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { can } from '../support/tenant-can';

const acme = (): NavTenant => ({
    slug: 'acme',
    role: 'owner',
    can: can(true, true),
});
const titles = (tenant: NavTenant) =>
    mainNavItems(tenant, false).map((item) => item.title);

describe('a union account', () => {
    const union: NavTenant = { ...acme(), type: 'union' };

    it('has no compliance tools in the sidebar', () => {
        expect(complianceNavItems(union)).toEqual([]);
        expect(complianceNavItems({ ...acme(), type: 'company' })).not.toEqual(
            [],
        );
    });

    // Board 40 (V4-Menu-Roles): a union's people are its members, «Agremiados».
    it('keeps the panel, its members and users, and drops the work centers', () => {
        expect(titles(union)).toEqual([
            'Panel',
            'Agremiados',
            'Usuarios',
            'Plan y uso',
        ]);
    });
});
