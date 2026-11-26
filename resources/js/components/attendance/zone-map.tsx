import 'leaflet/dist/leaflet.css';
import { useState } from 'react';
import ZoneProbeNote from '@/components/attendance/zone-probe-note';
import { useZonesMap } from '@/hooks/use-zones-map';
import { probeAt } from '@/lib/attendance/zone-map';
import type { MapZone, Probe } from '@/lib/attendance/zone-map';

/**
 * The map of a center's zones (board A2): OpenStreetMap streets with each zone's circle and name, and the test «¿contaría
 * una marca aquí?» for any point touched. The table of zones carries the same data for a screen reader.
 */
export default function ZoneMap({ zones }: { zones: MapZone[] }) {
    const [probe, setProbe] = useState<Probe | null>(null);
    const container = useZonesMap(zones, (point) =>
        setProbe(probeAt(zones, point)),
    );

    return (
        <div className="flex flex-col gap-3">
            <div
                ref={container}
                role="application"
                aria-label={`Mapa de las zonas: ${zones.length} ${zones.length === 1 ? 'círculo' : 'círculos'}; toca un punto para probar si una marca contaría`}
                className="zone-picker-map isolate h-72 w-full cursor-crosshair overflow-hidden rounded-xl border md:h-96"
            />
            <ZoneProbeNote probe={probe} />
        </div>
    );
}
