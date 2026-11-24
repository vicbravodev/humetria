import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vite-plus/test';

import { navSections } from '@/lib/nav-sections';
import { toUrl } from '@/lib/utils';
import { can } from '../support/tenant-can';

/**
 * The menu's order, written down once for both sides: the sidebar draws it here, and StartRoute (PHP) walks the same pages
 * to find where a person lands; StartRouteMenuOrderTest holds StartRoute to the same file.
 */
const order: unknown = JSON.parse(
    readFileSync(
        new URL('../../Fixtures/navigation/menu-order.json', import.meta.url),
        'utf8',
    ),
);

describe('the order of the menu', () => {
    it('is the one written in tests/Fixtures/navigation/menu-order.json, which StartRoute walks', () => {
        const pages = navSections({
            tenant: { slug: 'acme', role: 'owner', can: can(true, true) },
            isStaff: false,
        })
            .flatMap((section) => section.items)
            .map((item) => toUrl(item.href).replace('/admin/acme', ''));

        expect(pages).toEqual(order);
    });
});
