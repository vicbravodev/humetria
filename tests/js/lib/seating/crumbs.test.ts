import { describe, expect, it } from 'vite-plus/test';

import { seatingCrumbs } from '@/lib/seating/crumbs';

describe('seatingCrumbs', () => {
    it('leads back to the centers and to the route of the center', () => {
        const crumbs = seatingCrumbs(
            'acme',
            { id: 'c1', name: 'Planta Querétaro' },
            { title: 'Plan' },
        );

        expect(crumbs.map((crumb) => crumb.title)).toEqual([
            'Ley Silla',
            'Planta Querétaro',
            'Plan',
        ]);
        expect(crumbs[0]?.href).toEqual(
            expect.objectContaining({ url: '/admin/acme/seating' }),
        );
        expect(crumbs[1]?.href).toEqual(
            expect.objectContaining({
                url: '/admin/acme/seating/centers/c1/route',
            }),
        );
    });
});
