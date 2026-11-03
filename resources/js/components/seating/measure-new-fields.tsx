import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import SeatChoiceField from '@/components/seating/seat-choice-field';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { positions } from '@/routes/tenant/seating/seats';
import type { SeatOption } from '@/types';

type Errors = Partial<Record<string, string>>;

/** What a new measure is and what it is for: its kind, a note for «other» and the position (searched on the server). */
export default function MeasureNewFields({
    workCenterId,
    kinds,
    errors,
}: {
    workCenterId: string;
    kinds: SeatOption[];
    errors: Errors;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <SeatChoiceField
                name="kind"
                legend="Tipo de medida"
                options={kinds}
                value={undefined}
                error={errors['kind']}
            />
            <div className="grid gap-2">
                <Label htmlFor="measure-note">
                    Qué es (obligatorio si es «otra medida»)
                </Label>
                <Textarea id="measure-note" name="note" rows={2} />
                <InputError message={errors['note']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="measure-position">
                    Puesto al que corresponde
                </Label>
                <Combobox
                    id="measure-position"
                    name="position_id"
                    source={serverSource(
                        positions.url({
                            tenant: tenantSlug,
                            workCenter: workCenterId,
                        }),
                    )}
                    placeholder="Busca por nombre"
                    invalid={errors['position_id'] !== undefined}
                    describedBy="measure-position-error"
                />
                <InputError
                    id="measure-position-error"
                    message={errors['position_id']}
                />
            </div>
        </>
    );
}
