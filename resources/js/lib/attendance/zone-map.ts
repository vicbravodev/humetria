/**
 * The plan of a center's zones (board A2): its circles drawn to scale on a flat view, and the test «¿contaría una marca
 * aquí?» for a point RH touches. Distances are meters on a local flat projection, exact enough at the size of a center.
 */

export type MapZone = {
    id: string;
    name: string;
    radius: number;
    latitude: number;
    longitude: number;
};

/** The zones that know where they are (only whoever may change them is told), as the plan draws them. */
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

export type MapSize = { width: number; height: number };

/** A zone as a circle of the view: its center and radius in the view's units. */
export type ZoneShape = {
    id: string;
    name: string;
    cx: number;
    cy: number;
    r: number;
};

const METERS_PER_DEGREE_LATITUDE = 110_540;
const METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR = 111_320;
/** Room around the circles, as a share of the view. */
const MARGIN = 0.12;

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

/**
 * The zones as circles of a view of `size`, to one scale, east to the right and north up, all inside the view with a
 * margin. `metersPerUnit` says how long a unit of the view is, for the scale bar and for reading a touch back.
 */
export function layoutZones(
    zones: readonly MapZone[],
    size: MapSize,
): {
    shapes: ZoneShape[];
    metersPerUnit: number;
    origin: { lat: number; lng: number };
    /** The view's top-left corner, in meters east and north of `origin`. */
    corner: Meters;
} {
    const first = zones[0];

    if (first === undefined) {
        return {
            shapes: [],
            metersPerUnit: 1,
            origin: { lat: 0, lng: 0 },
            corner: { x: 0, y: 0 },
        };
    }

    const origin = place(first);
    const placed = zones.map((zone) => ({
        zone,
        at: toMeters(place(zone), origin),
    }));
    const left = Math.min(...placed.map(({ zone, at }) => at.x - zone.radius));
    const right = Math.max(...placed.map(({ zone, at }) => at.x + zone.radius));
    const bottom = Math.min(
        ...placed.map(({ zone, at }) => at.y - zone.radius),
    );
    const top = Math.max(...placed.map(({ zone, at }) => at.y + zone.radius));
    const unitsPerMeter = Math.min(
        (size.width * (1 - 2 * MARGIN)) / (right - left),
        (size.height * (1 - 2 * MARGIN)) / (top - bottom),
    );
    const cornerX = (left + right) / 2 - size.width / 2 / unitsPerMeter;
    const cornerY = (top + bottom) / 2 + size.height / 2 / unitsPerMeter;

    return {
        shapes: placed.map(({ zone, at }) => ({
            id: zone.id,
            name: zone.name,
            cx: (at.x - cornerX) * unitsPerMeter,
            cy: (cornerY - at.y) * unitsPerMeter,
            r: zone.radius * unitsPerMeter,
        })),
        metersPerUnit: 1 / unitsPerMeter,
        origin,
        corner: { x: cornerX, y: cornerY },
    };
}

/** Where a touch of the view is on the earth, for `probeAt`. */
export function pointAt(
    view: { x: number; y: number },
    layout: Pick<
        ReturnType<typeof layoutZones>,
        'metersPerUnit' | 'origin' | 'corner'
    >,
): { lat: number; lng: number } {
    const east = layout.corner.x + view.x * layout.metersPerUnit;
    const north = layout.corner.y - view.y * layout.metersPerUnit;

    return {
        lat: layout.origin.lat + north / METERS_PER_DEGREE_LATITUDE,
        lng:
            layout.origin.lng +
            east /
                (METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR *
                    Math.cos((layout.origin.lat * Math.PI) / 180)),
    };
}

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

/** What RH reads after touching the plan: a lead in bold, the rest, and whether the mark would count. */
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

const ROUND_METERS = [10, 20, 50, 100, 200, 500, 1000, 2000, 5000];

/** The longest round length in meters that takes at most a third of the view, with its width in units and its label. */
export function scaleBar(
    metersPerUnit: number,
    viewWidth: number,
): { meters: number; width: number; label: string } {
    const fits = ROUND_METERS.filter(
        (meters) => meters / metersPerUnit <= viewWidth / 3,
    );
    const meters = fits.at(-1) ?? ROUND_METERS[0] ?? 10;

    return { meters, width: meters / metersPerUnit, label: `${meters} m` };
}
