import { describe, expect, it } from 'vite-plus/test';

import {
    mainNavItems,
    settingsEntryHref,
    settingsNavItems,
} from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';

describe('mainNavItems', () => {
    it("points Panel at the current company's panel", () => {
        const [panel] = mainNavItems('acme');

        expect(panel?.title).toBe('Panel');
        expect(toUrl(panel?.href ?? '')).toBe('/admin/acme');
    });

    it('points Panel at /admin when there is no company', () => {
        expect(toUrl(mainNavItems(null)[0]?.href ?? '')).toBe('/admin');
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
