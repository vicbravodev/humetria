import { describe, expect, it } from 'vite-plus/test';

import { mainNavItems } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

const acme = (viewWorkerAccess: boolean, type?: 'union') => ({
    slug: 'acme',
    role: 'owner' as const,
    ...(type === undefined ? {} : { type }),
    can: { ...can(true, true), view_worker_access: viewWorkerAccess },
});

const accesos = (tenant: ReturnType<typeof acme>) =>
    mainNavItems(tenant, false).find((item) => item.title === 'Accesos');

describe('Accesos in the menu', () => {
    it("is there, after the employees, for whoever may see the people's access", () => {
        expect(toUrl(accesos(acme(true))?.href ?? '')).toBe(
            '/admin/acme/access',
        );
    });

    it('is not there for whoever may not see it, nor for a union', () => {
        expect(accesos(acme(false))).toBeUndefined();
        expect(accesos(acme(true, 'union'))).toBeUndefined();
    });
});
