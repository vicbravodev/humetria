import { describe, expect, it } from 'vitest';
import {
    programStartText,
    programTrail,
    SCOPE_LABELS,
} from '@/lib/interventions';
import { index, show } from '@/routes/tenant/campaigns';

describe('programStartText', () => {
    it('says the results ask for a Programa', () => {
        expect(programStartText('required').title).toBe(
            'Esta evaluación pide un Programa de intervención',
        );
    });

    it('explains why a sample under the minimum gets no rows', () => {
        expect(programStartText('below_minimum').body).toContain(
            'ninguna fila',
        );
    });

    it('says the norm asks for none when nobody is in risk', () => {
        expect(programStartText('not_required').body).toContain('num. 8.3');
    });
});

describe('SCOPE_LABELS', () => {
    it('names what a row attends', () => {
        expect(SCOPE_LABELS).toEqual({
            final: 'Calificación final',
            category: 'Categoría',
            domain: 'Dominio',
        });
    });
});

describe('programTrail', () => {
    it('goes from NOM-035 through the evaluation to the Programa', () => {
        expect(
            programTrail('acme', {
                id: 'c1',
                workCenter: 'Planta Norte',
                kindLabel: 'Evaluación NOM-035',
                closedOn: '19 nov 2026',
                guide: 'Guía II',
            }),
        ).toEqual([
            { title: 'NOM-035', href: index('acme') },
            { title: 'Planta Norte', href: show(['acme', 'c1']) },
            { title: 'Programa de intervención' },
        ]);
    });
});
