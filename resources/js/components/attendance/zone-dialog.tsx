import ZoneFields from '@/components/attendance/zone-fields';
import ZoneMapPanel from '@/components/attendance/zone-map-panel';
import FormDialog from '@/components/humetria/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useZoneDraft } from '@/hooks/use-zone-draft';
import {
    clampRadius,
    initialSearch,
    parseCoordinate,
} from '@/lib/attendance/zone-picker';
import type { Point } from '@/lib/attendance/zone-picker';
import { store, update } from '@/routes/tenant/attendance/zones';
import type { AttendanceZone } from '@/types';

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
    const draft = useZoneDraft(zone);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            wide
            title={zone === null ? 'Agregar zona' : `Cambiar ${zone.name}`}
            description="Las personas de este centro podrán marcar dentro del círculo. Toca el mapa para poner su centro y arrastra el borde para cambiar el radio."
            form={zoneForm(tenantSlug, centerId, zone)}
            submit="Guardar zona"
            blocked={draft.blocked}
        >
            {(errors) => (
                <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_280px]">
                    <ZoneMapPanel
                        center={drawn(draft.latitude, draft.longitude)}
                        address={initialSearch(zone, centerAddress)}
                        radius={clampRadius(Number(draft.radius) || 100)}
                        onCenter={draft.place}
                        onRadius={(meters) => draft.setRadius(String(meters))}
                    />
                    <ZoneFields draft={draft} zone={zone} errors={errors} />
                </div>
            )}
        </FormDialog>
    );
}
