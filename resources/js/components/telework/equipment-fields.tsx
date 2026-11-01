import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EQUIPMENT_KINDS } from '@/lib/telework/equipment';

/** The kind, description and delivery day of an item of equipment. */
export default function EquipmentFields({
    today,
    errors,
}: {
    today: string;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="equipment-kind">Tipo</Label>
                <Combobox
                    id="equipment-kind"
                    name="kind"
                    source={{ options: EQUIPMENT_KINDS }}
                    placeholder="Elige una opción"
                    invalid={errors['kind'] !== undefined}
                    describedBy="equipment-kind-error"
                />
                <InputError
                    id="equipment-kind-error"
                    role="alert"
                    message={errors['kind']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="equipment-description">Descripción</Label>
                <Input
                    id="equipment-description"
                    name="description"
                    maxLength={500}
                    aria-invalid={
                        errors['description'] === undefined ? undefined : true
                    }
                    aria-describedby="equipment-description-error"
                />
                <InputError
                    id="equipment-description-error"
                    role="alert"
                    message={errors['description']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="equipment-date">Fecha de entrega</Label>
                <DatePicker
                    id="equipment-date"
                    name="delivered_on"
                    defaultValue={today}
                    invalid={errors['delivered_on'] !== undefined}
                    describedBy="equipment-date-error"
                />
                <InputError
                    id="equipment-date-error"
                    role="alert"
                    message={errors['delivered_on']}
                />
            </div>
        </>
    );
}
