import { describe, expect, it } from 'vite-plus/test';

import { navTrail } from '@/lib/app-navigation';

describe('navTrail with a link that opens its page on a view', () => {
    it('names a page whose sidebar link opens it on a view', () => {
        const desk = [
            {
                label: 'Ventas',
                items: [
                    {
                        title: 'Solicitudes de demo',
                        href: '/admin/demo-requests?view=board',
                    },
                ],
            },
        ];

        expect(navTrail(desk, '/admin/demo-requests')).toEqual([
            { title: 'Ventas' },
            { title: 'Solicitudes de demo' },
        ]);
    });
});
