import { describe, expect, it } from 'vite-plus/test';

import {
    mainNavItems,
    settingsEntryHref,
    settingsNavItems,
} from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';

const can = (manage_organization: boolean, manage_members: boolean) => ({
    manage_organization,
    manage_members,
});
const acme = (manageOrganization = true, manageMembers = true) => ({
    slug: 'acme',
    can: can(manageOrganization, manageMembers),
});
const titles = (tenant: ReturnType<typeof acme> | null) =>
    mainNavItems(tenant).map((item) => item.title);

describe('mainNavItems', () => {
    it("points Panel at the current company's panel", () => {
        const [panel] = mainNavItems(acme());

        expect(panel?.title).toBe('Panel');
        expect(toUrl(panel?.href ?? '')).toBe('/admin/acme');
    });

    it('points Panel at /admin when there is no company, with nothing else', () => {
        expect(toUrl(mainNavItems(null)[0]?.href ?? '')).toBe('/admin');
        expect(titles(null)).toEqual(['Panel']);
    });

    it("lists the company's pages the role may open", () => {
        expect(titles(acme())).toEqual([
            'Panel',
            'Centros de trabajo',
            'Usuarios',
        ]);
        expect(titles(acme(true, false))).toEqual([
            'Panel',
            'Centros de trabajo',
        ]);
        expect(titles(acme(false, false))).toEqual([
            'Panel',
            'Centros de trabajo',
        ]);
    });

    it('points each page at the current company', () => {
        expect(mainNavItems(acme()).map((item) => toUrl(item.href))).toEqual([
            '/admin/acme',
            '/admin/acme/work-centers',
            '/admin/acme/members',
        ]);
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
