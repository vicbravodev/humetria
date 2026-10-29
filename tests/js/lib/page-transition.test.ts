import { describe, expect, it } from 'vite-plus/test';

import { pageTransition } from '@/lib/page-transition';

const here = 'http://localhost/admin/companies';

describe('pageTransition', () => {
    it('animates a plain visit to another page', () => {
        expect(pageTransition('/admin/demo-requests', {}, here)).toEqual({
            viewTransition: true,
        });
        expect(
            pageTransition('http://localhost/admin', { method: 'get' }, here),
        ).toEqual({ viewTransition: true });
    });

    it.each([
        ['a form submission', '/admin/companies', { method: 'post' as const }],
        ['a partial reload', '/admin/companies', { only: ['tenants'] }],
        ['the same page with another query', '/admin/companies?page=2', {}],
        ['an anchor on the same page', '/admin/companies#top', {}],
    ])('leaves %s still', (_, href, options) => {
        expect(pageTransition(href, options, here)).toEqual({
            viewTransition: false,
        });
    });

    it('keeps a transition the visit asked for itself', () => {
        expect(
            pageTransition('/admin/companies', { viewTransition: true }, here),
        ).toEqual({ viewTransition: true });
    });
});
