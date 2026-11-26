import { describe, expect, it } from 'vitest';
import { mapZones, probeAt, probeText } from '@/lib/attendance/zone-map';

const nave = {
    id: 'z1',
    name: 'Nave 1',
    radius: 80,
    latitude: 19.4326,
    longitude: -99.1332,
};
// About 150 m east of the Nave, at the same latitude (1 degree of longitude is 105 km at 19.4 N).
const patio = { ...nave, id: 'z2', name: 'Patio', longitude: -99.13178 };

describe('probeAt', () => {
    it('says a point inside a zone counts, with its distance to the center', () => {
        const result = probeAt([nave, patio], { lat: 19.4326, lng: -99.1332 });

        expect(result).toEqual({ inside: true, zone: 'Nave 1', distance: 0 });
    });

    it('takes the nearest zone, and a point on the edge as inside', () => {
        // 80 m east of the Nave is its edge (1 degree of longitude = 105 km here).
        const edge = probeAt([nave], {
            lat: 19.4326,
            lng: -99.1332 + 80 / 105000,
        });

        expect(edge.inside).toBe(true);
        expect(edge.distance).toBeGreaterThanOrEqual(79);
        expect(edge.distance).toBeLessThanOrEqual(81);
    });

    it('says a point outside every zone does not count, naming the nearest', () => {
        const result = probeAt([nave, patio], {
            lat: 19.4326,
            lng: -99.1332 - 0.002,
        });

        expect(result.inside).toBe(false);
        expect(result.zone).toBe('Nave 1');
        expect(result.distance).toBeGreaterThan(150);
    });
});

describe('probeText', () => {
    it('words the answer for RH', () => {
        expect(
            probeText({ inside: true, zone: 'Nave 1', distance: 49 }),
        ).toEqual({
            tone: 'ok',
            lead: 'Dentro de Nave 1',
            text: 'a 49 m del centro. Aquí una marca se acepta.',
        });
        expect(
            probeText({ inside: false, zone: 'Nave 1', distance: 230 }),
        ).toEqual({
            tone: 'out',
            lead: 'Fuera de las zonas',
            text: 'a 230 m del centro de Nave 1. Aquí una marca no cuenta.',
        });
    });
});

describe('mapZones', () => {
    it('keeps only the zones that say where they are', () => {
        expect(
            mapZones([
                {
                    id: 'a',
                    name: 'Con lugar',
                    radius: 50,
                    latitude: 1,
                    longitude: 2,
                },
                { id: 'b', name: 'Sin lugar', radius: 50 },
                { id: 'c', name: 'Sin longitud', radius: 50, latitude: 1 },
            ]),
        ).toEqual([
            {
                id: 'a',
                name: 'Con lugar',
                radius: 50,
                latitude: 1,
                longitude: 2,
            },
        ]);
    });
});
