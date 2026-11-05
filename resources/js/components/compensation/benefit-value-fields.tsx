import FormSection from '@/components/compensation/form-section';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import FormField from '@/components/form-field';
import { Label } from '@/components/ui/label';
import { useYearStart } from '@/hooks/use-year-start';

/** The first value of a new benefit and the day it starts to count, in the unit of how it is worked out. */
export default function BenefitValueFields({
    errors,
    unit,
}: {
    errors: Partial<Record<string, string>>;
    unit: string | undefined;
}) {
    const start = useYearStart();

    return (
        <FormSection title="Cuánto vale hoy">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="value"
                    idPrefix="benefit"
                    label={unit === undefined ? 'Valor' : `Valor (${unit})`}
                    inputMode="decimal"
                    autoComplete="off"
                    error={errors['value']}
                />
                <div className="grid gap-2">
                    <Label htmlFor="benefit-effective_from">
                        Vigente desde
                    </Label>
                    <DatePicker
                        id="benefit-effective_from"
                        name="effective_from"
                        defaultValue={start}
                        invalid={errors['effective_from'] !== undefined}
                        describedBy="benefit-effective_from-error"
                    />
                    <InputError
                        id="benefit-effective_from-error"
                        message={errors['effective_from']}
                    />
                </div>
            </div>
        </FormSection>
    );
}
