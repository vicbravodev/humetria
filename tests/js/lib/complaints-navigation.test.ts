import { describe, expect, it } from 'vite-plus/test';

import { activeChild, mainNavItems } from '@/lib/app-navigation';
import { handlesCasesOnly } from '@/lib/complaints-navigation';
import { listeningNavItems, operationsNavItems } from '@/lib/tool-navigation';
import { toUrl } from '@/lib/utils';
import { coverage, templateCan } from '../support/tenant-can';
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
    view_worker_access: false,
    manage_worker_access: false,
    ...coverage,
    view_attendance: false,
    manage_attendance: false,
    view_org_chart: false,
    manage_org_chart: false,
    view_vacations: false,
    manage_vacations: false,
    view_climate: false,
    manage_climate: false,
    view_pulse: false,
    manage_pulse: false,
});
const tenant = (
    role: CurrentTenant['role'],
    handle: boolean,
    manage: boolean,
) => ({
    slug: 'acme',
    role,
    // The outside handler carries what the server gives its template (handling or not), nothing more.
    can:
        role === 'complaints'
            ? { ...templateCan(role), handle_complaints: handle }
            : ability(handle, manage),
});

describe('the complaints in the navigation', () => {
    it('offers whoever handles and manages them the cases first, then the channel and who handles it', () => {
        const quejas = listeningNavItems(tenant('owner', true, true)).find(
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
        const quejas = listeningNavItems(tenant('owner', false, true)).find(
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
        const quejas = listeningNavItems(tenant('committee', true, false)).find(
            (item) => item.title === 'Quejas',
        );

        expect(quejas?.children?.map((child) => child.title)).toEqual([
            'Casos',
        ]);
    });

    it('offers nothing to a role that neither handles nor manages them', () => {
        expect(
            listeningNavItems(tenant('viewer', false, false)).some(
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
        expect(listeningNavItems(handler)).toEqual([]);
    });

    it('leaves an outside handler who cannot handle with nothing at all', () => {
        const handler = tenant('complaints', false, false);

        expect(mainNavItems(handler, false)).toEqual([]);
        expect(listeningNavItems(handler)).toEqual([]);
    });

    it('lights Canal on the poster and the evidence of the channel, and Casos on a case', () => {
        const children =
            listeningNavItems(tenant('owner', true, true)).find(
                (item) => item.title === 'Quejas',
            )?.children ?? [];
        const lit = (path: string) => activeChild(children, path)?.title;

        expect(lit('/admin/acme/complaints/poster')).toBe('Canal');
        expect(lit('/admin/acme/complaints/evidence')).toBe('Canal');
        expect(lit('/admin/acme/complaints/channel')).toBe('Canal');
        expect(lit('/admin/acme/complaints/cases/9')).toBe('Casos');
        expect(lit('/admin/acme/complaints')).toBe('Casos');
    });
});

describe("a role of the company's own that handles complaints", () => {
    const holder = (
        role: CurrentTenant['role'],
        can: CurrentTenant['can'],
    ) => ({
        slug: 'acme',
        role,
        can,
    });

    // A role of the company's own that handles complaints plus any other page keeps that page: «cases only» is read from the
    // whole `can`, not from a list of pages that can go stale.
    it.each([
        'view_vacations',
        'view_climate',
        'manage_compensation',
        'view_interventions',
    ])(
        'keeps what %s opens for a role that also handles complaints',
        (permission) => {
            const custom = holder(
                'custom_7',
                templateCan('complaints', [permission]),
            );

            expect(handlesCasesOnly(custom)).toBe(false);
        },
    );

    it('keeps Vacaciones in the menu of a custom role that handles complaints and views vacations', () => {
        const custom = holder(
            'custom_7',
            templateCan('complaints', ['view_vacations']),
        );

        expect(operationsNavItems(custom).map((item) => item.title)).toContain(
            'Vacaciones',
        );
    });

    it('still reduces the complaints template and a custom role made the same to their cases', () => {
        expect(
            handlesCasesOnly(holder('complaints', templateCan('complaints'))),
        ).toBe(true);
        expect(
            handlesCasesOnly(
                holder(
                    'custom_7',
                    templateCan('complaints', ['use_assistant']),
                ),
            ),
        ).toBe(true);
    });

    it('does not reduce the templates that open more than complaints', () => {
        for (const template of [
            'owner',
            'hr',
            'viewer',
            'committee',
        ] as const) {
            expect(
                handlesCasesOnly(holder(template, templateCan(template))),
            ).toBe(false);
        }
    });
});
