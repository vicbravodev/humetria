import 'leaflet/dist/leaflet.css';
import { useZonePickerMap } from '@/hooks/use-zone-picker-map';
import type { ZonePickerProps } from '@/hooks/use-zone-picker-map';

/**
 * The map of a zone (board A2): streets of OpenStreetMap, the circle of the zone, its center to drag and its edge to
 * widen it. Touching the map puts the center there. The fields beside it carry the same data for the keyboard.
 */
export default function ZonePickerMap(props: ZonePickerProps) {
    const container = useZonePickerMap(props);

    return (
        <div
            ref={container}
            role="application"
            aria-label="Mapa de la zona: toca un punto para poner su centro, arrastra el borde para cambiar el radio"
            className="zone-picker-map isolate h-72 w-full overflow-hidden rounded-xl border md:h-full md:min-h-[420px]"
        />
    );
}
