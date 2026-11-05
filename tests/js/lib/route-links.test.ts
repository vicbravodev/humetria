import { describe, expect, it } from 'vite-plus/test';
import { dossierHref, LIVE, stageHrefs } from '@/lib/route-links';
import { toUrl } from '@/lib/utils';

const urls = (norm: 'nom035' | 'nom037' | 'seating') =>
    stageHrefs(norm, 'acme', 'c1').map(toUrl);

describe('stageHrefs', () => {
    it('leads each of the six stages to the page where it is done, the dossier last', () => {
        // Rediseño v2: NOM-037 has its own route page (board V2-Ruta-N037), where each of its stages is done.
        expect(urls('nom037')).toEqual([
            ...Array(5).fill('/admin/acme/telework/centers/c1/route'),
            '/admin/acme/telework/centers/c1/dossier',
        ]);
        expect(urls('nom035')).toEqual([
            '/admin/acme/disclosure',
            '/admin/acme/campaigns',
            '/admin/acme/campaigns',
            '/admin/acme/disclosure',
            '/admin/acme/programs',
            '/admin/acme/disclosure/dossier/c1',
        ]);
        expect(urls('seating')).toEqual([
            ...Array(5).fill('/admin/acme/seating/centers/c1'),
            '/admin/acme/seating/centers/c1/dossier',
        ]);
    });
});

describe('dossierHref', () => {
    it('is the dossier each module prints for the center', () => {
        expect(toUrl(dossierHref('seating', 'acme', 'c1'))).toBe(
            '/admin/acme/seating/centers/c1/dossier',
        );
    });
});

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
