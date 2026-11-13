import {
    circle as createCircle,
    control,
    divIcon,
    map as createMap,
    marker as createMarker,
    tileLayer,
} from 'leaflet';
import type { Circle, LeafletMouseEvent, Map, Marker } from 'leaflet';
import { useEffect, useRef } from 'react';
import type { MapView } from '@/hooks/use-address-view';
import { STREET_ZOOM, edgeOf, radiusTo } from '@/lib/attendance/zone-picker';
import type { Point } from '@/lib/attendance/zone-picker';

export type ZonePickerProps = {
    center: Point | null;
    radius: number;
    /** Where to look while the zone has no center yet (the address of its work center); the map stays on Mexico without it. */
    view?: MapView | null;
    /** `zoom` is how close the map was when the center was touched or dragged; it is not given for a typed or searched one. */
    onCenter: (point: Point, zoom?: number) => void;
    onRadius: (meters: number) => void;
};

type Layers = { circle: Circle; pin: Marker; edge: Marker };

/** Mexico whole, until the zone has a center; then the street. */
const MEXICO: Point = { lat: 23.6, lng: -102.5 };
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

const handle = (className: string) =>
    divIcon({
        className: '',
        html: `<span class="block size-full rounded-full ${className}"></span>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });

function openMap(element: HTMLElement, center: Point | null): Map {
    const map = createMap(element, { attributionControl: false }).setView(
        center ?? MEXICO,
        center === null ? 5 : STREET_ZOOM,
    );
    tileLayer(TILES, { maxZoom: 19 }).addTo(map);
    control
        .attribution({ prefix: false })
        .addAttribution('© OpenStreetMap')
        .addTo(map);

    return map;
}

/** Where to look while the zone has no center and how close; nothing to look at, and the street's zoom, without a view. */
function viewParts(
    view: MapView | null | undefined,
): [number | null, number | null, number] {
    return view === null || view === undefined
        ? [null, null, STREET_ZOOM]
        : [view.lat, view.lng, view.zoom];
}

/** The circle, the pin at its center and the handle on its edge, both dragged; the fields follow the drag. */
function drawLayers(
    map: Map,
    props: ZonePickerProps,
    dragging: { edge: boolean },
): Layers {
    const { center, radius } = props;
    const at = center ?? MEXICO;
    const circle = createCircle(at, {
        radius,
        weight: 2,
        fillOpacity: 1,
        className: 'fill-primary/15 stroke-primary',
    }).addTo(map);
    const pin = createMarker(at, {
        draggable: true,
        keyboard: false,
        icon: handle('border-[3px] border-primary bg-card shadow-md'),
    }).addTo(map);
    const edge = createMarker(edgeOf(at, radius), {
        draggable: true,
        keyboard: false,
        icon: handle('bg-primary ring-2 ring-card shadow-md cursor-ew-resize'),
    }).addTo(map);

    pin.on('drag', () => props.onCenter(pin.getLatLng(), map.getZoom()));
    edge.on('dragstart', () => {
        dragging.edge = true;
    });
    edge.on('drag', () => {
        const meters = radiusTo(pin.getLatLng(), edge.getLatLng());
        circle.setRadius(meters);
        props.onRadius(meters);
    });
    edge.on('dragend', () => {
        dragging.edge = false;
        edge.setLatLng(edgeOf(pin.getLatLng(), circle.getRadius()));
    });

    return { circle, pin, edge };
}

function removeLayers(layers: Layers | null): null {
    layers?.circle.remove();
    layers?.pin.remove();
    layers?.edge.remove();

    return null;
}

/** Moves the drawing to the fields' center and radius; brings the center into view when it is off-screen. */
function syncLayers(
    map: Map,
    layers: Layers,
    zone: { center: Point; radius: number; edgeDragged: boolean },
) {
    layers.circle.setLatLng(zone.center).setRadius(zone.radius);
    layers.pin.setLatLng(zone.center);

    if (!zone.edgeDragged) {
        layers.edge.setLatLng(edgeOf(zone.center, zone.radius));
    }

    if (!map.getBounds().contains(zone.center)) {
        map.setView(zone.center, Math.max(map.getZoom(), STREET_ZOOM));
    }
}

/**
 * A Leaflet map of OpenStreetMap streets for a zone's center and radius: a touch puts the center, the pin and the edge
 * are dragged, and the circle follows what is typed. Off-screen centers (a search, the location) are brought into view.
 */
export function useZonePickerMap(props: ZonePickerProps) {
    const container = useRef<HTMLDivElement>(null);
    const map = useRef<Map | null>(null);
    const layers = useRef<Layers | null>(null);
    const latest = useRef(props);
    const dragging = useRef({ edge: false });
    const { radius } = props;
    const lat = props.center?.lat ?? null;
    const lng = props.center?.lng ?? null;
    const [viewLat, viewLng, viewZoom] = viewParts(props.view);

    useEffect(() => {
        latest.current = props;
    });

    useEffect(() => {
        const element = container.current;

        if (element === null) {
            return;
        }

        const opened = openMap(element, latest.current.center);
        opened.on('click', (event: LeafletMouseEvent) =>
            latest.current.onCenter(event.latlng, opened.getZoom()),
        );
        // The dialog opens with an animation: the map measures itself again once it has its size.
        const observer = new ResizeObserver(() => opened.invalidateSize());
        observer.observe(element);
        map.current = opened;

        return () => {
            observer.disconnect();
            opened.remove();
            map.current = null;
            layers.current = null;
        };
    }, []);

    // The zone's work center is found: look there, unless the zone already has a center of its own.
    useEffect(() => {
        if (
            map.current !== null &&
            viewLat !== null &&
            viewLng !== null &&
            layers.current === null
        ) {
            map.current.setView({ lat: viewLat, lng: viewLng }, viewZoom);
        }
    }, [viewLat, viewLng, viewZoom]);

    useEffect(() => {
        const opened = map.current;

        if (opened === null) {
            return;
        }

        if (lat === null || lng === null) {
            layers.current = removeLayers(layers.current);

            return;
        }

        const center = { lat, lng };

        // The first circle drawn takes the map from the whole country down to its street.
        if (layers.current === null) {
            opened.setView(center, Math.max(opened.getZoom(), STREET_ZOOM));
        }

        layers.current ??= drawLayers(
            opened,
            {
                center,
                radius,
                onCenter: (point, zoom) => latest.current.onCenter(point, zoom),
                onRadius: (meters) => latest.current.onRadius(meters),
            },
            dragging.current,
        );
        syncLayers(opened, layers.current, {
            center,
            radius,
            edgeDragged: dragging.current.edge,
        });
    }, [lat, lng, radius]);

    return container;
}
