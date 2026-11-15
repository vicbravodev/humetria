import { describe, expect, it } from 'vite-plus/test';
import { dossierNote, dossierStanding } from '@/lib/nom035/dossier';

describe('dossierStanding', () => {
    it('names every state in words', () => {
        expect(dossierStanding('done')).toEqual({
            label: 'Lo tienes',
            variant: 'success',
        });
        expect(dossierStanding('partial').label).toBe('En curso');
        expect(dossierStanding('missing')).toEqual({
            label: 'Falta',
            variant: 'destructive',
        });
        expect(dossierStanding('not_applicable').label).toBe('No aplica');
    });
});

describe('dossierNote', () => {
    it('says what the dossier includes', () => {
        expect(dossierNote('4 nov 2026', false)).toBe(
            'Emitido el 4 nov 2026. Incluye lo que el centro tiene y lo que falta, marcado como pendiente.',
        );
        expect(dossierNote('4 nov 2026', true)).toBe(
            'Emitido el 4 nov 2026. Solo incluye lo que el centro puede mostrar; lo que falta no aparece.',
        );
    });
});
