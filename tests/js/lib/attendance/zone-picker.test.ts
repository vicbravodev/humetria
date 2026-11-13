import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    clampRadius,
    edgeOf,
    findPlaces,
    initialSearch,
    parseCoordinate,
    radiusTo,
} from '@/lib/attendance/zone-picker';

describe('parseCoordinate', () => {
    it('reads a typed degree within its limit', () => {
        expect(parseCoordinate(' 19.432712 ', 90)).toBe(19.432712);
        expect(parseCoordinate('-99.1332', 180)).toBe(-99.1332);
        expect(parseCoordinate('90', 90)).toBe(90);
        expect(parseCoordinate('-180', 180)).toBe(-180);
    });

    it('gives nothing for what is not a degree or is past its limit', () => {
        expect(parseCoordinate('', 90)).toBeNull();
        expect(parseCoordinate('   ', 90)).toBeNull();
        expect(parseCoordinate('norte', 90)).toBeNull();
        expect(parseCoordinate('90.000001', 90)).toBeNull();
        expect(parseCoordinate('-180.5', 180)).toBeNull();
        expect(parseCoordinate('Infinity', 180)).toBeNull();
    });
});

describe('clampRadius', () => {
    it('keeps the radius in whole meters between 10 and 5000, as the server accepts it', () => {
        expect(clampRadius(80.4)).toBe(80);
        expect(clampRadius(80.5)).toBe(81);
        expect(clampRadius(9)).toBe(10);
        expect(clampRadius(10)).toBe(10);
        expect(clampRadius(5000)).toBe(5000);
        expect(clampRadius(5001)).toBe(5000);
    });
});

describe('edgeOf and radiusTo', () => {
    it('puts the handle of the radius due east of the center, as far as the radius', () => {
        // Leaflet draws a circle on a sphere of 6,371 km: a degree there is 111,195 m.
        const edge = edgeOf({ lat: 0, lng: 0 }, 1000);

        expect(edge.lat).toBe(0);
        expect(edge.lng).toBeCloseTo(0.008993, 6);
    });

    it('widens the step in longitude away from the equator', () => {
        expect(edgeOf({ lat: 60, lng: -99 }, 1000).lng).toBeCloseTo(
            -98.982014,
            6,
        );
    });

    it('reads back the radius from where the handle was dropped', () => {
        const center = { lat: 19.4326, lng: -99.1332 };

        expect(radiusTo(center, edgeOf(center, 250))).toBe(250);
        expect(radiusTo(center, center)).toBe(10);
        expect(radiusTo(center, { lat: 21, lng: -99.1332 })).toBe(5000);
    });
});

describe('findPlaces', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    const answer = (status: number, body: unknown) =>
        vi.fn(() =>
            Promise.resolve(
                new Response(JSON.stringify(body), {
                    status,
                    headers: { 'Content-Type': 'application/json' },
                }),
            ),
        );

    it('asks the route for the address and keeps what has the shape of a place', async () => {
        const fetch = answer(200, {
            places: [
                { name: 'Planta Querétaro', latitude: 20.5, longitude: -100.4 },
                { name: 'Sin coordenadas' },
                'nada',
            ],
        });
        vi.stubGlobal('fetch', fetch);

        await expect(
            findPlaces(
                '/admin/acme/attendance/places',
                'Planta  ',
                new AbortController().signal,
            ),
        ).resolves.toEqual([
            { name: 'Planta Querétaro', latitude: 20.5, longitude: -100.4 },
        ]);
        expect(fetch).toHaveBeenCalledWith(
            '/admin/acme/attendance/places?address=Planta',
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
    });

    it('finds nothing in an answer without places', async () => {
        vi.stubGlobal('fetch', answer(200, { other: [] }));

        await expect(
            findPlaces('/places', 'Planta', new AbortController().signal),
        ).resolves.toEqual([]);
    });

    it('fails when the route says the search is down', async () => {
        vi.stubGlobal('fetch', answer(503, { message: 'x' }));

        await expect(
            findPlaces('/places', 'Planta', new AbortController().signal),
        ).rejects.toThrow('Place search failed');
    });
});

describe('initialSearch', () => {
    const located = {
        id: 'z',
        name: 'Nave 1',
        radius: 80,
        latitude: 19.4,
        longitude: -99.1,
    };
    const unlocated = { id: 'z', name: 'Nave 1', radius: 80 };
    const address =
        'Reforma 222, col. Juárez, C.P. 06600, Cuauhtémoc, Ciudad de México';

    it('is the address of the center for a zone that has no center yet', () => {
        expect(initialSearch(null, address)).toBe(address);
        expect(initialSearch(unlocated, address)).toBe(address);
    });

    it('is nothing for a zone that already has a center, or when the center has no address', () => {
        expect(initialSearch(located, address)).toBeNull();
        expect(initialSearch(null, null)).toBeNull();
        expect(initialSearch(null, '   ')).toBeNull();
    });

    it('is nothing for a search the server would not make (under 3 letters)', () => {
        expect(initialSearch(null, 'ab')).toBeNull();
    });
});
