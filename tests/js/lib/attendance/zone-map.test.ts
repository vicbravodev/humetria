import { describe, expect, it } from 'vitest';
import {
    layoutZones,
    mapZones,
    pointAt,
    probeAt,
    probeText,
    scaleBar,
} from '@/lib/attendance/zone-map';

const SIZE = { width: 600, height: 300 };
const nave = {
    id: 'z1',
    name: 'Nave 1',
    radius: 80,
    latitude: 19.4326,
    longitude: -99.1332,
};
// About 150 m east of the Nave, at the same latitude (1 degree of longitude is 105 km at 19.4 N).
const patio = { ...nave, id: 'z2', name: 'Patio', longitude: -99.13178 };

describe('layoutZones', () => {
    it('draws nothing without zones', () => {
        expect(layoutZones([], SIZE).shapes).toEqual([]);
    });

    it('centers one zone and makes its circle as large as the view allows', () => {
        const { shapes } = layoutZones([nave], SIZE);

        expect(shapes).toHaveLength(1);
        expect(shapes[0]?.cx).toBeCloseTo(300);
        expect(shapes[0]?.cy).toBeCloseTo(150);
        expect(shapes[0]?.r).toBeGreaterThan(50);
        expect(shapes[0]?.r).toBeLessThan(150);
    });

    it('keeps every circle inside the view, east to the right and north up', () => {
        const { shapes } = layoutZones(
            [
                nave,
                patio,
                { ...nave, id: 'z3', name: 'Norte', latitude: 19.4338 },
            ],
            SIZE,
        );
        const [a, b, c] = shapes;

        expect(b?.cx).toBeGreaterThan(a?.cx ?? 0);
        expect(c?.cy).toBeLessThan(a?.cy ?? 0);
        for (const shape of shapes) {
            expect(shape.cx - shape.r).toBeGreaterThanOrEqual(0);
            expect(shape.cx + shape.r).toBeLessThanOrEqual(SIZE.width);
            expect(shape.cy - shape.r).toBeGreaterThanOrEqual(0);
            expect(shape.cy + shape.r).toBeLessThanOrEqual(SIZE.height);
        }
    });

    it('draws the same distance in meters at the same length', () => {
        const { shapes } = layoutZones([nave, patio], SIZE);
        const [a, b] = shapes;

        // The two centers are about 150 m apart, and the radius is 80 m.
        const apart = (b?.cx ?? 0) - (a?.cx ?? 0);
        expect(apart / (a?.r ?? 1)).toBeCloseTo(150 / 80, 1);
    });
});

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

describe('scaleBar', () => {
    it('picks a round length that fits a third of the view', () => {
        const { metersPerUnit } = layoutZones([nave, patio], SIZE);
        const bar = scaleBar(metersPerUnit, SIZE.width);

        expect([10, 20, 50, 100, 200, 500, 1000]).toContain(bar.meters);
        expect(bar.width).toBeLessThanOrEqual(SIZE.width / 3);
        expect(bar.width).toBeCloseTo(bar.meters / metersPerUnit);
        expect(bar.label).toBe(`${bar.meters} m`);
    });
});

describe('pointAt', () => {
    it('reads a touch of the view back as the place it draws', () => {
        const layout = layoutZones([nave, patio], SIZE);

        for (const [index, zone] of [nave, patio].entries()) {
            const shape = layout.shapes[index];
            const point = pointAt(
                { x: shape?.cx ?? 0, y: shape?.cy ?? 0 },
                layout,
            );

            expect(point.lat).toBeCloseTo(zone.latitude, 6);
            expect(point.lng).toBeCloseTo(zone.longitude, 6);
        }
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
