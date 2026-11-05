import { describe, expect, it } from 'vite-plus/test';

import { teleworkCrumbs } from '@/lib/telework/crumbs';

describe('teleworkCrumbs', () => {
    it('leads back to the centers and to the route of the center', () => {
        const crumbs = teleworkCrumbs('acme', { id: 'c1', name: 'Planta Querétaro' }, { title: 'Comisión' });

        expect(crumbs.map((crumb) => crumb.title)).toEqual(['NOM-037', 'Planta Querétaro', 'Comisión']);
        expect(crumbs[0]?.href).toEqual(expect.objectContaining({ url: '/admin/acme/telework' }));
        expect(crumbs[1]?.href).toEqual(expect.objectContaining({ url: '/admin/acme/telework/centers/c1/route' }));
    });

    it('skips the center on a page of the whole company', () => {
        expect(teleworkCrumbs('acme', null, { title: 'Documentos' }).map((crumb) => crumb.title)).toEqual(['NOM-037', 'Documentos']);
    });
});
