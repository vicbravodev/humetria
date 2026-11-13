import ZoneFarNotice from '@/components/attendance/zone-far-notice';
import ZoneLocationButton from '@/components/attendance/zone-location-button';
import FormField from '@/components/form-field';
import type { ZoneDraft } from '@/hooks/use-zone-draft';
import type { AttendanceZone } from '@/types';

/** The fields beside the map of a zone: its name, where RH stands, the coordinates and the radius as typed. */
export default function ZoneFields({
    draft,
    zone,
    errors,
}: {
    draft: ZoneDraft;
    zone: AttendanceZone | null;
    errors: Partial<Record<string, string>>;
}) {
    const id = zone?.id ?? 'new';

    return (
        <div className="grid content-start gap-4">
            {draft.far && (
                <ZoneFarNotice
                    confirmed={draft.confirmed}
                    onConfirm={draft.setConfirmed}
                />
            )}
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
                    draft.place({
                        lat: reading.lat,
                        lng: reading.lng,
                    })
                }
            />
            <div className="grid grid-cols-2 gap-3">
                <FormField
                    name="latitude"
                    label="Latitud"
                    idPrefix={`zone-${id}`}
                    inputMode="decimal"
                    value={draft.latitude}
                    onChange={(event) => draft.typeLatitude(event.target.value)}
                    error={errors['latitude']}
                />
                <FormField
                    name="longitude"
                    label="Longitud"
                    idPrefix={`zone-${id}`}
                    inputMode="decimal"
                    value={draft.longitude}
                    onChange={(event) =>
                        draft.typeLongitude(event.target.value)
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
                value={draft.radius}
                onChange={(event) => draft.setRadius(event.target.value)}
                error={errors['radius_m']}
            />
        </div>
    );
}
