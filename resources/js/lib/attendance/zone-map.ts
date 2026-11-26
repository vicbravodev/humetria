/**
 * The zones of a center on its map (board A2), and the test «¿contaría una marca aquí?» for a point RH touches. Distances
 * are meters on a local flat projection, exact enough at the size of a center.
 */

export type MapZone = {
    id: string;
    name: string;
    radius: number;
    latitude: number;
    longitude: number;
};

/** The zones that know where they are (only whoever may change them is told), as the map draws them. */
export function mapZones(
    zones: readonly {
        id: string;
        name: string;
        radius: number;
        latitude?: number;
        longitude?: number;
    }[],
): MapZone[] {
    return zones.flatMap(({ latitude, longitude, ...zone }) =>
        latitude === undefined || longitude === undefined
            ? []
            : [{ ...zone, latitude, longitude }],
    );
}

const METERS_PER_DEGREE_LATITUDE = 110_540;
const METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR = 111_320;

type Meters = { x: number; y: number };

/** East and north of `origin`, in meters. */
function toMeters(
    point: { lat: number; lng: number },
    origin: { lat: number; lng: number },
): Meters {
    return {
        x:
            (point.lng - origin.lng) *
            METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR *
            Math.cos((origin.lat * Math.PI) / 180),
        y: (point.lat - origin.lat) * METERS_PER_DEGREE_LATITUDE,
    };
}

const place = (zone: MapZone) => ({ lat: zone.latitude, lng: zone.longitude });

export type Probe = { inside: boolean; zone: string; distance: number };

/** Whether a mark at `point` would fall inside a zone (its edge counts), and how far it is from the center of the nearest. */
export function probeAt(
    zones: readonly MapZone[],
    point: { lat: number; lng: number },
): Probe {
    const nearest = zones
        .map((zone) => {
            const at = toMeters(place(zone), point);

            return { zone, distance: Math.hypot(at.x, at.y) };
        })
        .toSorted(
            (a, b) => a.distance - a.zone.radius - (b.distance - b.zone.radius),
        )[0];

    if (nearest === undefined) {
        return { inside: false, zone: '', distance: 0 };
    }

    return {
        inside: nearest.distance <= nearest.zone.radius,
        zone: nearest.zone.name,
        distance: Math.round(nearest.distance),
    };
}

/** What RH reads after touching the map: a lead in bold, the rest, and whether the mark would count. */
export function probeText(probe: Probe): {
    tone: 'ok' | 'out';
    lead: string;
    text: string;
} {
    return probe.inside
        ? {
              tone: 'ok',
              lead: `Dentro de ${probe.zone}`,
              text: `a ${probe.distance} m del centro. Aquí una marca se acepta.`,
          }
        : {
              tone: 'out',
              lead: 'Fuera de las zonas',
              text: `a ${probe.distance} m del centro de ${probe.zone}. Aquí una marca no cuenta.`,
          };
}
