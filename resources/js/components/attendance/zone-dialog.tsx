import { useState } from 'react';
import ZoneLocationButton from '@/components/attendance/zone-location-button';
import ZoneMapPanel from '@/components/attendance/zone-map-panel';
import FormField from '@/components/form-field';
import FormDialog from '@/components/humetria/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { coordinate } from '@/lib/attendance/zone';
import {
    clampRadius,
    initialSearch,
    parseCoordinate,
} from '@/lib/attendance/zone-picker';
import type { Point } from '@/lib/attendance/zone-picker';
import { store, update } from '@/routes/tenant/attendance/zones';
import type { AttendanceZone } from '@/types';

const text = (value: number | undefined): string =>
    value === undefined ? '' : coordinate(value);

/** The zone the map draws while the fields hold a valid center; a radius being typed draws as the server would take it. */
function drawn(latitude: string, longitude: string): Point | null {
    const lat = parseCoordinate(latitude, 90);
    const lng = parseCoordinate(longitude, 180);

    return lat === null || lng === null ? null : { lat, lng };
}

function zoneForm(
    tenant: string,
    workCenter: string,
    zone: AttendanceZone | null,
) {
    return zone === null
        ? store.form({ tenant, workCenter })
        : update.form({ tenant, workCenter, zone: zone.id });
}

/**
 * A zone's name, center and radius, new or to change (board A2): placed on the map by touching it, dragging its pin and
 * edge, searching an address or taking where RH stands; the fields beside it follow and can be typed.
 */
export default function ZoneDialog({
    open,
    onOpenChange,
    centerId,
    centerAddress = null,
    zone,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    centerId: string;
    /** The center's address: where the map of a zone with no center yet opens. */
    centerAddress?: string | null;
    zone: AttendanceZone | null;
}) {
    const tenantSlug = useTenantSlug();
    const [latitude, setLatitude] = useState(text(zone?.latitude));
    const [longitude, setLongitude] = useState(text(zone?.longitude));
    const [radius, setRadius] = useState(String(zone?.radius ?? 100));
    const id = zone?.id ?? 'new';
    const place = (point: Point) => {
        setLatitude(coordinate(point.lat));
        setLongitude(coordinate(point.lng));
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            wide
            title={zone === null ? 'Agregar zona' : `Cambiar ${zone.name}`}
            description="Las personas de este centro podrán marcar dentro del círculo. Toca el mapa para poner su centro y arrastra el borde para cambiar el radio."
            form={zoneForm(tenantSlug, centerId, zone)}
            submit="Guardar zona"
        >
            {(errors) => (
                <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_280px]">
                    <ZoneMapPanel
                        center={drawn(latitude, longitude)}
                        address={initialSearch(zone, centerAddress)}
                        radius={clampRadius(Number(radius) || 100)}
                        onCenter={place}
                        onRadius={(meters) => setRadius(String(meters))}
                    />
                    <div className="grid content-start gap-4">
                        <FormField
                            name="name"
                            label="Nombre"
                            idPrefix={`zone-${id}`}
                            defaultValue={zone?.name ?? ''}
                            maxLength={60}
                            error={errors['name']}
                        />
                        <ZoneLocationButton
                            onFound={(reading) =>
                                place({ lat: reading.lat, lng: reading.lng })
                            }
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <FormField
                                name="latitude"
                                label="Latitud"
                                idPrefix={`zone-${id}`}
                                inputMode="decimal"
                                value={latitude}
                                onChange={(event) =>
                                    setLatitude(event.target.value)
                                }
                                error={errors['latitude']}
                            />
                            <FormField
                                name="longitude"
                                label="Longitud"
                                idPrefix={`zone-${id}`}
                                inputMode="decimal"
                                value={longitude}
                                onChange={(event) =>
                                    setLongitude(event.target.value)
                                }
                                error={errors['longitude']}
                            />
                        </div>
                        <FormField
                            name="radius_m"
                            label="Radio en metros"
                            idPrefix={`zone-${id}`}
                            type="number"
                            inputMode="numeric"
                            min={10}
                            max={5000}
                            value={radius}
                            onChange={(event) => setRadius(event.target.value)}
                            error={errors['radius_m']}
                        />
                    </div>
                </div>
            )}
        </FormDialog>
    );
}
