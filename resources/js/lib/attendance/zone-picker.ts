import type { AttendanceZone } from '@/types';

/** A point on the map of a zone, in degrees. */
export type Point = { lat: number; lng: number };

/** A place found by its address (route tenant.attendance.places). */
export type Place = { name: string; latitude: number; longitude: number };

/** The sphere Leaflet draws its circles on, so the handle sits exactly on the circle's edge. */
const EARTH_RADIUS_M = 6_371_000;
const METERS_PER_DEGREE = (EARTH_RADIUS_M * Math.PI) / 180;
const RADIANS = Math.PI / 180;

/** The radius the server accepts (ZoneRequest): whole meters from 10 to 5000. */
export const MIN_RADIUS = 10;
export const MAX_RADIUS = 5000;

/** A typed degree, or nothing while it is not one or is past its limit (90 for latitude, 180 for longitude). */
export function parseCoordinate(text: string, limit: 90 | 180): number | null {
    const value = Number(text.trim());

    return text.trim() === '' ||
        !Number.isFinite(value) ||
        Math.abs(value) > limit
        ? null
        : value;
}

export function clampRadius(meters: number): number {
    return Math.min(MAX_RADIUS, Math.max(MIN_RADIUS, Math.round(meters)));
}

/** Where the handle of the radius goes: due east of the center, as far as the radius. */
export function edgeOf(center: Point, meters: number): Point {
    return {
        lat: center.lat,
        lng:
            center.lng +
            meters / (METERS_PER_DEGREE * Math.cos(center.lat * RADIANS)),
    };
}

/** The radius for a handle dropped at `edge`: its great-circle distance to the center, within what the server accepts. */
export function radiusTo(center: Point, edge: Point): number {
    const dLat = (edge.lat - center.lat) * RADIANS;
    const dLng = (edge.lng - center.lng) * RADIANS;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(center.lat * RADIANS) *
            Math.cos(edge.lat * RADIANS) *
            Math.sin(dLng / 2) ** 2;

    return clampRadius(2 * EARTH_RADIUS_M * Math.asin(Math.sqrt(a)));
}

function isPlace(value: unknown): value is Place {
    return (
        typeof value === 'object' &&
        value !== null &&
        'name' in value &&
        'latitude' in value &&
        'longitude' in value &&
        typeof value.name === 'string' &&
        typeof value.latitude === 'number' &&
        typeof value.longitude === 'number'
    );
}

/** The places the route finds for an address; it throws when the search is down, so RH is told to use the map. */
export async function findPlaces(
    url: string,
    address: string,
    signal: AbortSignal,
): Promise<Place[]> {
    const response = await fetch(
        `${url}?${new URLSearchParams({ address: address.trim() }).toString()}`,
        { headers: { Accept: 'application/json' }, signal },
    );

    if (!response.ok) {
        throw new Error('Place search failed');
    }

    const body: unknown = await response.json();
    const places =
        typeof body === 'object' && body !== null && 'places' in body
            ? body.places
            : [];

    return Array.isArray(places) ? places.filter(isPlace) : [];
}

/** The address a new zone's map opens on: the center's own; none once the zone has a center, or when there is no address to search. */
export function initialSearch(
    zone: AttendanceZone | null,
    centerAddress: string | null,
): string | null {
    const address = centerAddress?.trim() ?? '';

    return zone?.latitude === undefined && address.length >= 3 ? address : null;
}

/** The map looks at a street for an address, and at a city for the fallback when the street is not found. */
export const STREET_ZOOM = 17;
export const CITY_ZOOM = 12;
/** A center touched on a map zoomed out beyond this is more likely a slip than a place: it is confirmed. */
const MIN_PLACING_ZOOM = 15;

export function tooFarToPlace(zoom: number): boolean {
    return zoom < MIN_PLACING_ZOOM;
}

/** The municipality and the state of an address (its last two parts), to look at the city when the street is not found. */
export function cityOf(address: string): string | null {
    const parts = address
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');

    return parts.length < 3 ? null : parts.slice(-2).join(', ');
}
