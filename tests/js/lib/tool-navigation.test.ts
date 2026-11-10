import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems } from '@/lib/app-navigation';
import { toolNavItems } from '@/lib/tool-navigation';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

const acme = (
    allowed = true,
    role: 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints' = 'owner',
) => ({ slug: 'acme', role, can: can(allowed, allowed) });

describe('toolNavItems', () => {
    it('offers the org chart, Clima and Compensación to whoever may view them in a company', () => {
        expect(
            toolNavItems(acme()).map((item) => [item.title, toUrl(item.href)]),
        ).toEqual([
            ['Organigrama', '/admin/acme/org-chart'],
            ['Clima', '/admin/acme/climate'],
            ['Compensación', '/admin/acme/compensation'],
        ]);
    });

    it('offers Clima alone to whoever may only view it, and none to a union', () => {
        const tenant = acme(false, 'viewer');
        const climate = {
            ...tenant,
            can: { ...tenant.can, view_climate: true },
        };

        expect(toolNavItems(climate).map((item) => item.title)).toEqual([
            'Clima',
        ]);
        expect(toolNavItems({ ...climate, type: 'union' })).toEqual([]);
    });

    it('lists the pages of the org chart under it', () => {
        expect(
            toolNavItems(acme())[0]?.children?.map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([
            ['Organigrama', '/admin/acme/org-chart'],
            ['Calidad de datos', '/admin/acme/org-chart/quality'],
        ]);
    });

    it('offers a union Compensación and no org chart: its roster has no hierarchy', () => {
        expect(
            toolNavItems({ ...acme(), type: 'union' }).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([['Compensación', '/admin/acme/compensation']]);
    });

    it('offers the org chart alone to whoever may only view it', () => {
        const tenant = acme(false, 'viewer');

        expect(
            toolNavItems({
                ...tenant,
                can: { ...tenant.can, view_org_chart: true },
            }).map((item) => item.title),
        ).toEqual(['Organigrama']);
    });

    it('offers nothing to a role that cannot view it, nor outside a company', () => {
        expect(toolNavItems(acme(false, 'viewer'))).toEqual([]);
        expect(toolNavItems(null)).toEqual([]);
    });

    it('keeps Compensación out of the compliance list, even for a union', () => {
        expect(
            complianceNavItems({ ...acme(), type: 'union' }).map(
                (item) => item.title,
            ),
        ).toEqual([]);
        expect(
            complianceNavItems(acme()).map((item) => item.title),
        ).not.toContain('Compensación');
    });
});
