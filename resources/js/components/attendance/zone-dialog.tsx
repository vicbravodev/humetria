import { useState } from 'react';
import ZoneLocationButton from '@/components/attendance/zone-location-button';
import FormField from '@/components/form-field';
import FormDialog from '@/components/humetria/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { coordinate } from '@/lib/attendance/zone';
import { store, update } from '@/routes/tenant/attendance/zones';
import type { AttendanceZone } from '@/types';

const text = (value: number | undefined): string =>
    value === undefined ? '' : coordinate(value);

/** A zone's name, center and radius, new or to change; its center typed or taken from where RH stands. */
export default function ZoneDialog({
    open,
    onOpenChange,
    centerId,
    zone,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    centerId: string;
    zone: AttendanceZone | null;
}) {
    const tenantSlug = useTenantSlug();
    const [latitude, setLatitude] = useState(text(zone?.latitude));
    const [longitude, setLongitude] = useState(text(zone?.longitude));
    const id = zone?.id ?? 'new';

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={zone === null ? 'Agregar zona' : `Cambiar ${zone.name}`}
            description="Las personas de este centro podrán marcar dentro del círculo: su centro y su radio en metros."
            form={
                zone === null
                    ? store.form({ tenant: tenantSlug, workCenter: centerId })
                    : update.form({
                          tenant: tenantSlug,
                          workCenter: centerId,
                          zone: zone.id,
                      })
            }
            submit="Guardar zona"
        >
            {(errors) => (
                <div className="grid gap-4">
                    <FormField
                        name="name"
                        label="Nombre"
                        idPrefix={`zone-${id}`}
                        defaultValue={zone?.name ?? ''}
                        maxLength={60}
                        error={errors['name']}
                    />
                    <ZoneLocationButton
                        onFound={(reading) => {
                            setLatitude(coordinate(reading.lat));
                            setLongitude(coordinate(reading.lng));
                        }}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
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
                        defaultValue={zone?.radius ?? 100}
                        error={errors['radius_m']}
                    />
                </div>
            )}
        </FormDialog>
    );
}
