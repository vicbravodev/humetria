import { circle as createCircle, control, latLngBounds } from 'leaflet';
import type { LeafletMouseEvent } from 'leaflet';
import { useEffect, useRef } from 'react';
import { openMap } from '@/hooks/use-zone-picker-map';
import type { MapZone } from '@/lib/attendance/zone-map';
import type { Point } from '@/lib/attendance/zone-picker';

/**
 * A Leaflet map of OpenStreetMap streets with a center's zones: each a circle with its name, the map fitted to all of
 * them, a scale in meters, and every touch handed to `onTouch`. It is drawn again only when `zones` changes.
 */
export function useZonesMap(
    zones: readonly MapZone[],
    onTouch: (point: Point) => void,
) {
    const container = useRef<HTMLDivElement>(null);
    const latest = useRef(onTouch);

    useEffect(() => {
        latest.current = onTouch;
    });

    useEffect(() => {
        const element = container.current;

        if (element === null) {
            return;
        }

        const opened = openMap(element, null);
        const bounds = latLngBounds([]);

        for (const zone of zones) {
            const drawn = createCircle(
                { lat: zone.latitude, lng: zone.longitude },
                {
                    radius: zone.radius,
                    weight: 2,
                    fillOpacity: 1,
                    className: 'fill-primary/15 stroke-primary',
                },
            )
                .bindTooltip(zone.name, {
                    permanent: true,
                    direction: 'center',
                })
                .addTo(opened);
            bounds.extend(drawn.getBounds());
        }

        if (bounds.isValid()) {
            opened.fitBounds(bounds, { padding: [24, 24] });
        }

        control.scale({ imperial: false }).addTo(opened);
        opened.on('click', (event: LeafletMouseEvent) =>
            latest.current(event.latlng),
        );
        const observer = new ResizeObserver(() => opened.invalidateSize());
        observer.observe(element);

        return () => {
            observer.disconnect();
            opened.remove();
        };
    }, [zones]);

    return container;
}
