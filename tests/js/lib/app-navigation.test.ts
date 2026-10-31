import { describe, expect, it } from 'vite-plus/test';

import {
    complianceNavItems,
    mainNavItems,
    navTrail,
    nextObligation,
    settingsEntryHref,
    settingsNavItems,
} from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';

const can = (manage_organization: boolean, manage_members: boolean) => ({
    manage_organization,
    manage_members,
    manage_campaigns: manage_organization,
    view_trauma_screenings: manage_organization,
});
const acme = (
    manageOrganization = true,
    manageMembers = true,
    role: 'owner' | 'hr' | 'viewer' | 'committee' = 'owner',
) => ({
    slug: 'acme',
    role,
    can: can(manageOrganization, manageMembers),
});
const titles = (tenant: ReturnType<typeof acme> | null) =>
    mainNavItems(tenant, false).map((item) => item.title);

describe('mainNavItems', () => {
    it("points Panel at the current company's panel", () => {
        const [panel] = mainNavItems(acme(), false);

        expect(panel?.title).toBe('Panel');
        expect(toUrl(panel?.href ?? '')).toBe('/admin/acme');
    });

    it('points Panel at /admin when there is no company, with nothing else', () => {
        expect(toUrl(mainNavItems(null, false)[0]?.href ?? '')).toBe('/admin');
        expect(titles(null)).toEqual(['Panel']);
    });

    it('leaves Panel out for a superadmin outside a company: /admin sends them to Empresas', () => {
        expect(mainNavItems(null, true)).toEqual([]);
    });

    it('keeps the company pages for a superadmin inside a company they belong to', () => {
        expect(
            mainNavItems(acme(), true).map((item) => toUrl(item.href)),
        ).toEqual([
            '/admin/acme',
            '/admin/acme/work-centers',
            '/admin/acme/employees',
            '/admin/acme/members',
        ]);
    });

    it("lists the company's pages the role may open", () => {
        expect(titles(acme())).toEqual([
            'Panel',
            'Centros de trabajo',
            'Empleados',
            'Usuarios',
        ]);
        expect(titles(acme(true, false))).toEqual([
            'Panel',
            'Centros de trabajo',
            'Empleados',
        ]);
        expect(titles(acme(false, false))).toEqual([
            'Panel',
            'Centros de trabajo',
        ]);
    });

    it('points each page at the current company', () => {
        expect(
            mainNavItems(acme(), false).map((item) => toUrl(item.href)),
        ).toEqual([
            '/admin/acme',
            '/admin/acme/work-centers',
            '/admin/acme/employees',
            '/admin/acme/members',
        ]);
    });
});

describe('mainNavItems for the committee', () => {
    it('shows nothing: its only page is NOM-037', () => {
        expect(titles(acme(false, false, 'committee'))).toEqual([]);
    });
});

describe('complianceNavItems', () => {
    it('offers NOM-035 inside a company, to every role', () => {
        expect(
            complianceNavItems(acme(false, false)).map((item) => [
                item.title,
                toUrl(item.href),
            ]),
        ).toEqual([['NOM-035', '/admin/acme/campaigns']]);
    });

    it('offers nothing outside a company', () => {
        expect(complianceNavItems(null)).toEqual([]);
    });

    it('offers the committee nothing yet: NOM-037 has no page', () => {
        expect(complianceNavItems(acme(false, false, 'committee'))).toEqual([]);
    });

    it('counts the evaluations in course on NOM-035', () => {
        expect(complianceNavItems(acme(), 2)[0]?.badge).toBe(2);
        expect(complianceNavItems(acme())[0]?.badge).toBe(0);
    });
});

describe('nextObligation', () => {
    it('tells when the soonest evaluation closes', () => {
        expect(nextObligation({ count: 1, daysLeft: 11, elapsed: 21 })).toEqual(
            { lead: 'La próxima evaluación cierra', when: 'en 11 días' },
        );
        expect(
            nextObligation({ count: 2, daysLeft: 1, elapsed: 90 })?.when,
        ).toBe('mañana');
        expect(
            nextObligation({ count: 1, daysLeft: 0, elapsed: 100 })?.when,
        ).toBe('hoy');
    });

    it('has none without an evaluation in course', () => {
        expect(nextObligation({ count: 0, daysLeft: null, elapsed: 0 })).toBe(
            null,
        );
        expect(nextObligation(null)).toBe(null);
    });
});

describe('settingsNavItems', () => {
    it('lists profile, security and appearance for the owner themselves', () => {
        expect(settingsNavItems(false).map((item) => item.title)).toEqual([
            'Perfil',
            'Seguridad',
            'Apariencia',
        ]);
    });

    it("leaves only appearance while impersonating: the owner's account is not the superadmin's to touch", () => {
        expect(settingsNavItems(true).map((item) => item.title)).toEqual([
            'Apariencia',
        ]);
    });
});

describe('settingsEntryHref', () => {
    it('opens profile, or appearance while impersonating', () => {
        expect(toUrl(settingsEntryHref(false))).toBe('/admin/settings/profile');
        expect(toUrl(settingsEntryHref(true))).toBe(
            '/admin/settings/appearance',
        );
    });
});

describe('navTrail', () => {
    const sections = [
        { label: 'Acme', items: mainNavItems(acme(), false) },
        { label: 'Cumplimiento', items: complianceNavItems(acme()) },
    ];

    it('names the section and the page on a page of the sidebar', () => {
        expect(navTrail(sections, '/admin/acme/employees')).toEqual([
            { title: 'Acme' },
            { title: 'Empleados' },
        ]);
    });

    it('links back to the sidebar page a deeper page hangs from', () => {
        const [crumb, ...rest] = navTrail(sections, '/admin/acme/campaigns/7');

        expect(crumb?.title).toBe('NOM-035');
        expect(toUrl(crumb?.href ?? '')).toBe('/admin/acme/campaigns');
        expect(rest).toEqual([]);
    });

    it('does not take the Panel for the parent of every company page', () => {
        expect(navTrail(sections, '/admin/acme/unknown')).toEqual([]);
    });

    it('has nothing to say outside the sidebar', () => {
        expect(navTrail(sections, '/admin/settings/profile')).toEqual([]);
    });
});
