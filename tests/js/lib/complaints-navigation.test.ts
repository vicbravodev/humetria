import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';
import type { CurrentTenant } from '@/types';

const ability = (handle: boolean, manage: boolean): CurrentTenant['can'] => ({
    manage_organization: manage,
    manage_members: manage,
    manage_campaigns: manage,
    view_trauma_screenings: manage,
    manage_telework: manage,
    review_workplaces: manage,
    view_seating: manage,
    manage_seating: manage,
    assess_seating: manage,
    view_compensation: manage,
    manage_compensation: manage,
    view_interventions: manage,
    manage_interventions: manage,
    view_disclosure: manage,
    manage_disclosure: manage,
    use_assistant: true,
    manage_complaint_channel: manage,
    handle_complaints: handle,
});
const tenant = (
    role: CurrentTenant['role'],
    handle: boolean,
    manage: boolean,
) => ({ slug: 'acme', role, can: ability(handle, manage) });

describe('the complaints in the navigation', () => {
    it('offers whoever handles and manages them the cases first, then the channel and who handles it', () => {
        const quejas = complianceNavItems(tenant('owner', true, true)).find(
            (item) => item.title === 'Quejas',
        );

        expect(toUrl(quejas?.href ?? '')).toBe('/admin/acme/complaints');
        expect(
            quejas?.children?.map((child) => [child.title, toUrl(child.href)]),
        ).toEqual([
            ['Casos', '/admin/acme/complaints'],
            ['Canal', '/admin/acme/complaints/channel'],
            ['Quiénes atienden', '/admin/acme/complaints/handlers'],
        ]);
    });

    it('starts a manager who handles none at the channel', () => {
        const quejas = complianceNavItems(tenant('owner', false, true)).find(
            (item) => item.title === 'Quejas',
        );

        expect(toUrl(quejas?.href ?? '')).toBe(
            '/admin/acme/complaints/channel',
        );
        expect(quejas?.children?.map((child) => child.title)).toEqual([
            'Canal',
            'Quiénes atienden',
        ]);
    });

    it('offers the cases alone to a handler who does not manage the channel', () => {
        const quejas = complianceNavItems(
            tenant('committee', true, false),
        ).find((item) => item.title === 'Quejas');

        expect(quejas?.children?.map((child) => child.title)).toEqual([
            'Casos',
        ]);
    });

    it('offers nothing to a role that neither handles nor manages them', () => {
        expect(
            complianceNavItems(tenant('viewer', false, false)).some(
                (item) => item.title === 'Quejas',
            ),
        ).toBe(false);
    });

    // Board 40 (V4-Menu-Roles): the outside handler's menu is its cases, with no section of the company around them.
    // Rediseño v2 (board V4-Menu-Roles): the outside handler also reads «Cómo se atiende».
    it('leaves the outside handler with its cases, how they are handled and nothing else of the company', () => {
        const handler = tenant('complaints', true, false);

        expect(
            mainNavItems(handler, false).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([
            ['Casos', '/admin/acme/complaints'],
            ['Cómo se atiende', '/admin/acme/complaints/procedure'],
        ]);
        expect(complianceNavItems(handler)).toEqual([]);
    });

    it('leaves an outside handler who cannot handle with nothing at all', () => {
        const handler = tenant('complaints', false, false);

        expect(mainNavItems(handler, false)).toEqual([]);
        expect(complianceNavItems(handler)).toEqual([]);
    });
});
