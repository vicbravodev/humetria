import { describe, expect, it } from 'vite-plus/test';

import { engagementTrail } from '@/lib/engagement/trail';

describe('engagementTrail', () => {
    it('opens every trail of Clima and Pulso with their family', () => {
        expect(engagementTrail('Pulso', '/pulse')).toEqual([
            { title: 'Escucha a tu gente' },
            { title: 'Pulso' },
        ]);
    });

    it('links the tool once something follows it, then the rest in order', () => {
        expect(
            engagementTrail(
                'Clima',
                '/climate',
                { title: 'Clima · junio 2026', href: '/climate/1' },
                { title: 'Resultados' },
            ),
        ).toEqual([
            { title: 'Escucha a tu gente' },
            { title: 'Clima', href: '/climate' },
            { title: 'Clima · junio 2026', href: '/climate/1' },
            { title: 'Resultados' },
        ]);
    });
});
