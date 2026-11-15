import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

/** When the plan starts, and the price agreed for this company, if there is one, with the last day it holds. */
export default function SubscriptionPriceFields({
    startsOn,
    price,
    validUntil,
    errors,
}: {
    startsOn: string;
    price: string;
    validUntil: string;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor="plan-starts_on">Empieza el</Label>
                <DatePicker
                    id="plan-starts_on"
                    name="starts_on"
                    defaultValue={startsOn}
                    invalid={errors['starts_on'] !== undefined}
                    describedBy="plan-starts_on-error"
                />
                <InputError
                    id="plan-starts_on-error"
                    message={errors['starts_on']}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    idPrefix="plan"
                    name="negotiated_price"
                    label="Precio negociado (opcional)"
                    inputMode="decimal"
                    prefix="$"
                    defaultValue={price}
                    error={errors['negotiated_price']}
                />
                <div className="grid gap-2">
                    <Label htmlFor="plan-negotiated_valid_until">
                        Vale hasta (opcional)
                    </Label>
                    <DatePicker
                        id="plan-negotiated_valid_until"
                        name="negotiated_valid_until"
                        defaultValue={validUntil}
                        invalid={errors['negotiated_valid_until'] !== undefined}
                        describedBy="plan-negotiated_valid_until-error"
                    />
                    <InputError
                        id="plan-negotiated_valid_until-error"
                        message={errors['negotiated_valid_until']}
                    />
                </div>
            </div>
        </div>
    );
}
