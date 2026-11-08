import { describe, expect, it } from 'vitest';
import {
    accuracyText,
    coordinate,
    locationErrorText,
    placeText,
    radiusText,
} from '@/lib/attendance/zone';

describe('coordinate', () => {
    it('writes a degree with six decimals, about 10 cm', () => {
        expect(coordinate(19.4327123456)).toBe('19.432712');
        expect(coordinate(-99)).toBe('-99.000000');
    });
});

describe('placeText', () => {
    it('reads a zone center as latitude, longitude', () => {
        expect(placeText(19.432712, -99.133298)).toBe('19.432712, -99.133298');
    });
});

describe('radiusText', () => {
    it('gives the radius in meters', () => {
        expect(radiusText(80)).toBe('80 m');
        expect(radiusText(5000)).toBe('5,000 m');
    });
});

describe('accuracyText', () => {
    it('says how far off the reading may be, rounded up', () => {
        expect(accuracyText(12.2)).toBe(
            'Tu ubicación tiene una precisión de ±13 m.',
        );
    });

    it('warns when the reading is coarser than a small zone', () => {
        expect(accuracyText(150.4)).toBe(
            'Tu ubicación tiene una precisión de ±151 m: muévete al aire libre o revisa las coordenadas antes de guardar.',
        );
    });
});

describe('locationErrorText', () => {
    it('says what happened and what to do instead, for each reason', () => {
        expect(locationErrorText('denied')).toBe(
            'Tu navegador no dio permiso de usar tu ubicación. Permítelo en la configuración del sitio o escribe las coordenadas.',
        );
        expect(locationErrorText('unavailable')).toBe(
            'No se pudo saber dónde estás. Intenta al aire libre o escribe las coordenadas.',
        );
        expect(locationErrorText('timeout')).toBe(
            'Tardó demasiado en encontrar tu ubicación. Intenta de nuevo o escribe las coordenadas.',
        );
        expect(locationErrorText('unsupported')).toBe(
            'Este navegador no puede dar tu ubicación. Escribe las coordenadas.',
        );
    });
});
