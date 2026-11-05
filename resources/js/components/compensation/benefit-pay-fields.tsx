import ChoiceField from '@/components/compensation/choice-field';
import OptionCards from '@/components/compensation/option-cards';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useYearStart } from '@/hooks/use-year-start';
import { KIND_HINTS } from '@/lib/compensation/benefit-draft';
import type { ChoiceOption } from '@/types';

/** «¿Cómo se paga?», «Cuánto», «Cada cuándo» and from when: what a new benefit is worth (board V4-Comp-Prestacion). */
export default function BenefitPayFields({
    kinds,
    frequencies,
    kind,
    onKind,
    errors,
}: {
    kinds: (ChoiceOption & { unit: string })[];
    frequencies: ChoiceOption[];
    kind: string;
    onKind: (kind: string) => void;
    errors: Partial<Record<string, string>>;
}) {
    const start = useYearStart();
    const unit = kinds.find((option) => option.value === kind)?.unit;

    return (
        <>
            <OptionCards
                name="kind"
                legend="¿Cómo se paga?"
                options={kinds.map((option) => ({
                    ...option,
                    hint: KIND_HINTS[option.value],
                }))}
                value={kind}
                columns={2}
                onChange={onKind}
            />
            <div className="grid items-start gap-4 sm:grid-cols-3">
                <FormField
                    name="value"
                    idPrefix="benefit"
                    label={unit === undefined ? 'Cuánto' : `Cuánto (${unit})`}
                    inputMode="decimal"
                    autoComplete="off"
                    error={errors['value']}
                />
                <ChoiceField
                    name="frequency"
                    label="Cada cuándo"
                    source={{ options: frequencies }}
                    defaultValue={frequencies.find(
                        (option) => option.value === 'monthly',
                    )}
                    error={errors['frequency']}
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
        </>
    );
}
