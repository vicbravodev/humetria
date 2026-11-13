import ZonePickerMap from '@/components/attendance/zone-picker-map';
import ZonePlaceSearch from '@/components/attendance/zone-place-search';
import type { ZonePickerProps } from '@/hooks/use-zone-picker-map';
import { useAddressView } from '@/hooks/use-address-view';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { places } from '@/routes/tenant/attendance';

/**
 * The map side of a zone's dialog: the address search above the map; a place found becomes the zone's center. With an
 * `address` (the center's) the map opens on it, once and without a word if nothing is found: the zone is still RH's to place.
 */
export default function ZoneMapPanel({
    address,
    ...props
}: Omit<ZonePickerProps, 'view'> & { address: string | null }) {
    const tenantSlug = useTenantSlug();
    const view = useAddressView(places.url(tenantSlug), address);

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
            <ZonePickerMap {...props} view={view} />
        </div>
    );
}
