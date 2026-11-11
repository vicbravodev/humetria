import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems } from '@/lib/app-navigation';
import { listeningNavItems, operationsNavItems } from '@/lib/tool-navigation';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

const acme = (
    allowed = true,
    role: 'owner' | 'hr' | 'viewer' | 'committee' | 'complaints' = 'owner',
) => ({ slug: 'acme', role, can: can(allowed, allowed) });

const links = (items: ReturnType<typeof listeningNavItems>) =>
    items.map((item) => [item.title, toUrl(item.href)]);

describe('listeningNavItems', () => {
    it('offers Quejas, Clima and Pulso to whoever may open them in a company', () => {
        expect(links(listeningNavItems(acme()))).toEqual([
            ['Quejas', '/admin/acme/complaints'],
            ['Clima', '/admin/acme/climate'],
            ['Pulso', '/admin/acme/pulse'],
        ]);
    });

    it('offers Clima alone to whoever may only view it, and none to a union', () => {
        const tenant = acme(false, 'viewer');
        const climate = {
            ...tenant,
            can: { ...tenant.can, view_climate: true },
        };

        expect(listeningNavItems(climate).map((item) => item.title)).toEqual([
            'Clima',
        ]);
        expect(listeningNavItems({ ...climate, type: 'union' })).toEqual([]);
    });

    it('offers Pulso alone to whoever may only view it, and none to a union', () => {
        const tenant = acme(false, 'viewer');
        const pulse = { ...tenant, can: { ...tenant.can, view_pulse: true } };

        expect(listeningNavItems(pulse).map((item) => item.title)).toEqual([
            'Pulso',
        ]);
        expect(listeningNavItems({ ...pulse, type: 'union' })).toEqual([]);
    });

    it('keeps Quejas out for a union and for the outside handler, whose cases are its whole menu', () => {
        expect(listeningNavItems({ ...acme(), type: 'union' })).toEqual([]);
        expect(
            listeningNavItems(acme(true, 'complaints')).map(
                (item) => item.title,
            ),
        ).not.toContain('Quejas');
    });
});

describe('operationsNavItems', () => {
    it('offers Vacaciones, the org chart and Compensación to whoever may view them in a company', () => {
        expect(links(operationsNavItems(acme()))).toEqual([
            ['Vacaciones', '/admin/acme/vacations'],
            ['Organigrama', '/admin/acme/org-chart'],
            ['Compensación', '/admin/acme/compensation'],
        ]);
    });

    it('lists the pages of the org chart under it', () => {
        expect(
            operationsNavItems(acme())
                .find((item) => item.title === 'Organigrama')
                ?.children?.map((item) => [item.title, toUrl(item.href)]),
        ).toEqual([
            ['Organigrama', '/admin/acme/org-chart'],
            ['Calidad de datos', '/admin/acme/org-chart/quality'],
        ]);
    });

    it('offers a union Compensación alone: its roster has no hierarchy and no vacations here', () => {
        expect(links(operationsNavItems({ ...acme(), type: 'union' }))).toEqual(
            [['Compensación', '/admin/acme/compensation']],
        );
    });

    it('offers the org chart alone to whoever may only view it', () => {
        const tenant = acme(false, 'viewer');

        expect(
            operationsNavItems({
                ...tenant,
                can: { ...tenant.can, view_org_chart: true },
            }).map((item) => item.title),
        ).toEqual(['Organigrama']);
    });

    it('keeps Vacaciones out for the outside handler of complaints', () => {
        expect(
            operationsNavItems(acme(true, 'complaints')).map(
                (item) => item.title,
            ),
        ).not.toContain('Vacaciones');
    });
});

describe('the families outside a company or without permission', () => {
    it('offer nothing to a role that cannot view them, nor outside a company', () => {
        expect(listeningNavItems(acme(false, 'viewer'))).toEqual([]);
        expect(operationsNavItems(acme(false, 'viewer'))).toEqual([]);
        expect(listeningNavItems(null)).toEqual([]);
        expect(operationsNavItems(null)).toEqual([]);
    });

    it('keep the standards alone under Cumple la norma, even for a union', () => {
        expect(
            complianceNavItems({ ...acme(), type: 'union' }).map(
                (item) => item.title,
            ),
        ).toEqual([]);
        expect(complianceNavItems(acme()).map((item) => item.title)).toEqual([
            'NOM-035',
            'NOM-037',
            'Ley Silla',
            'Asistencia',
        ]);
    });
});
