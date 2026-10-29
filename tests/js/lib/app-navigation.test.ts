import { describe, expect, it } from 'vite-plus/test';

import { mainNavItems } from '@/lib/app-navigation';
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
