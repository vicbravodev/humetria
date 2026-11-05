import { describe, expect, it } from 'vite-plus/test';
import { LIVE } from '@/lib/route-links';

describe('LIVE', () => {
    it('says what stays alive once each route is done (docs/02, «Ciclo de vida»)', () => {
        expect(LIVE.nom035.map(({ title }) => title)).toEqual([
            'Acciones del programa de intervención',
            'Quien entra nuevo recibe la política',
            'Si alguien vive un acontecimiento traumático severo',
            'Avisos de violencia o trato injusto',
        ]);
        expect(LIVE.nom037).toHaveLength(4);
        expect(LIVE.seating).toHaveLength(3);
    });
});
