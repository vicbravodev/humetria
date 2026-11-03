import { describe, expect, it } from 'vite-plus/test';

import { complianceNavItems, toolNavItems } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';

const can = (allowed: boolean) => ({
    manage_organization: allowed,
    manage_members: allowed,
    manage_campaigns: allowed,
    view_trauma_screenings: allowed,
    manage_telework: allowed,
    review_workplaces: allowed,
    view_seating: true,
    manage_seating: allowed,
    assess_seating: allowed,
    view_compensation: allowed,
    manage_compensation: allowed,
    use_assistant: true,
});
const acme = (
    allowed = true,
    role: 'owner' | 'hr' | 'viewer' | 'committee' = 'owner',
) => ({ slug: 'acme', role, can: can(allowed) });

describe('toolNavItems', () => {
    it('offers Compensación to whoever may view it, in a company and in a union', () => {
        const items = (tenant: ReturnType<typeof acme>, type?: 'union') =>
            toolNavItems({
                ...tenant,
                ...(type === undefined ? {} : { type }),
            });

        for (const type of [undefined, 'union' as const]) {
            expect(
                items(acme(), type).map((item) => [
                    item.title,
                    toUrl(item.href),
                ]),
            ).toEqual([['Compensación', '/admin/acme/compensation']]);
        }
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
