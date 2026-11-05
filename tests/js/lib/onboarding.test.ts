import { describe, expect, it } from 'vitest';
import {
    answered,
    createLabel,
    GUIDES,
    seatingTag,
    teleworkTag,
} from '@/lib/onboarding';
import type { CenterAnswers } from '@/lib/onboarding';

const none: CenterAnswers = {
    hasTeleworkers: null,
    standing: null,
    sector: null,
};
const plant: CenterAnswers = {
    hasTeleworkers: true,
    standing: true,
    sector: 'industrial',
};

describe('GUIDES', () => {
    it('names the NOM-035 guides of each size of center', () => {
        expect(GUIDES).toEqual({
            up_to_15: 'Guía I',
            '16_to_50': 'Guías I y II',
            over_50: 'Guías I y III',
        });
    });
});

describe('teleworkTag', () => {
    it('says whether NOM-037 applies, or that the question waits', () => {
        expect(teleworkTag(none)).toEqual({
            label: 'Falta contestar',
            tone: 'pending',
        });
        expect(teleworkTag(plant)).toEqual({ label: 'Aplica', tone: 'yes' });
        expect(teleworkTag({ ...none, hasTeleworkers: false })).toEqual({
            label: 'No aplica',
            tone: 'no',
        });
    });
});

describe('seatingTag', () => {
    it('says whether Ley Silla applies, in some positions for an industrial center, or what is missing', () => {
        expect(seatingTag(none).label).toBe('Falta contestar');
        expect(seatingTag({ ...none, standing: false })).toEqual({
            label: 'No aplica',
            tone: 'no',
        });
        expect(seatingTag({ ...none, standing: true })).toEqual({
            label: 'Falta el sector',
            tone: 'pending',
        });
        expect(seatingTag(plant)).toEqual({
            label: 'En algunos puestos',
            tone: 'yes',
        });
        expect(seatingTag({ ...plant, sector: 'commerce' })).toEqual({
            label: 'Aplica',
            tone: 'yes',
        });
    });
});

describe('answered', () => {
    it('needs both answers, and the sector wherever someone works standing', () => {
        expect(answered(plant)).toBe(true);
        expect(answered({ ...plant, sector: null })).toBe(false);
        expect(
            answered({ hasTeleworkers: false, standing: false, sector: null }),
        ).toBe(true);
        expect(answered(none)).toBe(false);
    });
});

describe('createLabel', () => {
    it('counts the centers left to answer, then the routes it will create', () => {
        expect(createLabel([plant, none])).toBe('Contesta los 2 centros');
        expect(createLabel([none])).toBe('Contesta el centro');
        expect(
            createLabel([
                plant,
                { hasTeleworkers: false, standing: false, sector: null },
            ]),
        ).toBe('Crear mis 4 rutas');
        expect(
            createLabel([
                { hasTeleworkers: false, standing: false, sector: null },
            ]),
        ).toBe('Crear mi ruta');
    });
});
