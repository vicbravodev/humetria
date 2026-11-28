import { describe, expect, it } from 'vitest';
import {
    nom035Result,
    RANGES,
    SECTORS,
    seatingResult,
    staffNote,
} from '@/lib/applicability';

describe('nom035Result', () => {
    it('gives every person the Guía I, and adds the II or the III by the size of the center (C-04, num. 4.4)', () => {
        expect(nom035Result('up_to_15', 9).title).toBe(
            'Guía I para todas las personas',
        );
        expect(nom035Result('16_to_50', 30).title).toBe(
            'Guías I y II para todas las personas',
        );
        expect(nom035Result('over_50', 60)).toMatchObject({
            title: 'Guías I y III',
            extra: 'Con 60 personas, la muestra mínima la ves al crear la evaluación.',
        });
    });

    it('says nothing of the sample when nobody is registered', () => {
        expect(nom035Result('over_50', 0).extra).toBe(
            'La muestra mínima la ves al crear la evaluación.',
        );
    });
});

describe('seatingResult', () => {
    it('asks first whether someone works standing', () => {
        expect(seatingResult(null, null)).toMatchObject({
            tag: 'Contesta para ver el resultado',
            applies: null,
        });
    });

    it('does not apply where nobody works standing, whatever the sector', () => {
        expect(seatingResult(false, 'commerce')).toMatchObject({
            tag: 'No te aplica',
            applies: false,
        });
    });

    it('waits for the sector, then applies in every position or only where the work allows a seat', () => {
        expect(seatingResult(true, null)).toMatchObject({
            tag: 'Casi',
            applies: null,
        });
        expect(seatingResult(true, 'industrial')).toMatchObject({
            tag: 'Te aplica en algunos puestos',
            applies: true,
        });
        expect(seatingResult(true, 'services')).toMatchObject({
            tag: 'Te aplica',
            title: 'En todos los puestos de pie',
            applies: true,
        });
    });
});

describe('choices', () => {
    it('offers the three sizes of num. 4.4 and the four sectors in plain words', () => {
        expect(RANGES.map(({ label }) => label)).toEqual([
            'Hasta 15 personas',
            'De 16 a 50 personas',
            'Más de 50 personas',
        ]);
        expect(SECTORS.map(({ label }) => label)).toEqual([
            'Comercio',
            'Servicios',
            'Industrial',
            'Otro parecido',
        ]);
    });
});

describe('staffNote', () => {
    it('says the size was marked from the people registered', () => {
        expect(staffNote(16, 'Planta', '16_to_50')).toBe(
            'Tienes 16 registradas en Planta. Lo marcamos por ti; las guías de cada evaluación salen de las personas que registres.',
        );
    });

    it('claims no size where the demo applies Guía II to a few people', () => {
        const note = staffNote(3, 'Sala', '16_to_50');

        expect(note).toBe(
            'Tienes 3 registradas en Sala. Aplican las Guías I y II: en esta demostración se aplican siempre, sin importar cuántas personas tenga el centro.',
        );
        expect(note).not.toMatch(/hasta 15|16 a 50/);
        expect(staffNote(15, 'Sala', 'up_to_15')).toContain(
            'Lo marcamos por ti',
        );
    });
});
