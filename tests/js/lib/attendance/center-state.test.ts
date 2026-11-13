import { describe, expect, it } from 'vitest';
import {
    centerFacts,
    centerState,
    notReadyNote,
} from '@/lib/attendance/center-state';

describe('centerState', () => {
    it('is not ready without a zone, whatever its screens: nobody marks with their location', () => {
        expect(centerState({ zones: 0, screens: 2 }, true)).toMatchObject({
            ready: false,
            label: 'Sin zonas',
            variant: 'destructive',
            action: 'Agregar la primera zona',
        });
    });

    it('asks for a screen when the policy asks for the QR on site and none shows the code', () => {
        expect(centerState({ zones: 1, screens: 0 }, true)).toMatchObject({
            ready: false,
            label: 'Falta pantalla QR',
            variant: 'warning',
            action: 'Activar pantalla QR',
            problem:
                'Tu política pide QR en el centro y la pantalla aún no está activa: hoy nadie puede marcar en sitio.',
        });
    });

    it('does not ask for a screen when the policy does not need the QR', () => {
        expect(centerState({ zones: 1, screens: 0 }, false)).toMatchObject({
            ready: true,
            label: 'Listo para marcar',
            variant: 'success',
            problem: null,
            action: null,
        });
    });

    it('is ready with a zone and a screen', () => {
        expect(centerState({ zones: 3, screens: 1 }, true).ready).toBe(true);
    });
});

describe('centerFacts', () => {
    it('counts its people, zones and active screens', () => {
        expect(centerFacts({ people: 5, zones: 2, screens: 0 })).toBe(
            '5 personas · 2 zonas · 0 pantallas activas',
        );
        expect(centerFacts({ people: 1, zones: 1, screens: 1 })).toBe(
            '1 persona · 1 zona · 1 pantalla activa',
        );
    });
});

describe('notReadyNote', () => {
    it('tells RH, before sending the links, that nobody can mark in the center yet', () => {
        expect(notReadyNote('Sucursal')).toBe(
            'Hoy nadie puede marcar en Sucursal: le falta una zona o una pantalla.',
        );
    });
});
