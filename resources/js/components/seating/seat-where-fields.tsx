import { MapPin } from 'lucide-react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { positions } from '@/routes/tenant/seating/seats';
import type { SeatEditing, SeatOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/** What the seat is, how many there are and where: the position (searched on the server) or the designated place. */
export default function SeatWhereFields({
    workCenterId,
    seat,
    kinds,
    uses,
    errors,
}: {
    workCenterId: string;
    seat: SeatEditing | null;
    kinds: SeatOption[];
    uses: SeatOption[];
    errors: Errors;
}) {
    const tenantSlug = useTenantSlug();
    const route = { tenant: tenantSlug, workCenter: workCenterId };

    return (
        <SettingRow
            icon={MapPin}
            title="El asiento y dónde está"
            description="Un renglón puede agrupar varios asientos iguales. Si es para los descansos, indica el área designada."
        >
            <div className="grid gap-5">
                <SeatChoiceField
                    name="kind"
                    legend="Tipo de asiento"
                    options={kinds}
                    value={seat?.kind}
                    error={errors['kind']}
                />
                <SeatChoiceField
                    name="use"
                    legend="Para qué sirve"
                    options={uses}
                    value={seat?.use}
                    error={errors['use']}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="seat-quantity">Cuántos son</Label>
                        <Input
                            id="seat-quantity"
                            name="quantity"
                            type="number"
                            min={1}
                            inputMode="numeric"
                            defaultValue={seat?.quantity ?? 1}
                        />
                        <InputError message={errors['quantity']} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="seat-position">Puesto (opcional)</Label>
                        <Combobox
                            id="seat-position"
                            name="position_id"
                            source={serverSource(positions.url(route))}
                            defaultValue={
                                seat?.positionId
                                    ? {
                                          value: seat.positionId,
                                          label: seat.positionName ?? '',
                                      }
                                    : undefined
                            }
                            placeholder="Busca por nombre"
                            invalid={errors['position_id'] !== undefined}
                            describedBy="seat-position-error"
                        />
                        <InputError
                            id="seat-position-error"
                            message={errors['position_id']}
                        />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="seat-location">Ubicación</Label>
                    <Input
                        id="seat-location"
                        name="location"
                        defaultValue={seat?.location ?? ''}
                        placeholder="Por ejemplo: comedor, planta baja"
                    />
                    <InputError message={errors['location']} />
                </div>
            </div>
        </SettingRow>
    );
}
