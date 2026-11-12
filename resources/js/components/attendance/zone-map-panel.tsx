import ZonePickerMap from '@/components/attendance/zone-picker-map';
import ZonePlaceSearch from '@/components/attendance/zone-place-search';
import type { ZonePickerProps } from '@/hooks/use-zone-picker-map';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { places } from '@/routes/tenant/attendance';

/** The map side of a zone's dialog: the address search above the map; a place found becomes the zone's center. */
export default function ZoneMapPanel(props: ZonePickerProps) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="flex min-w-0 flex-col gap-3">
            <ZonePlaceSearch
                url={places.url(tenantSlug)}
                onPick={(found) =>
                    props.onCenter({
                        lat: found.latitude,
                        lng: found.longitude,
                    })
                }
            />
            <ZonePickerMap {...props} />
        </div>
    );
}
