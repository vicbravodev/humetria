import ChoiceField from '@/components/compensation/choice-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import NumberStepper from '@/components/humetria/number-stepper';
import { Label } from '@/components/ui/label';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import { monthsLimit } from '@/lib/compensation/raise-draft';
import { stepMonths } from '@/lib/compensation/scenario-draft';
import { plural } from '@/lib/plural';

/** When the proposal starts, how many months back it pays (up to 36) and how many years it is costed over. */
export default function ScenarioPeriodFields({
    draft,
    onChange,
    horizons,
    errors,
}: {
    draft: RaiseDraft;
    onChange: (patch: Partial<RaiseDraft>) => void;
    horizons: number[];
    errors: Partial<Record<string, string>>;
}) {
    const horizonOptions = horizons.map((years) => ({
        value: String(years),
        label: `${years} años`,
    }));
    const limit = monthsLimit(draft.horizon);

    return (
        <div className="grid items-start gap-5 sm:grid-cols-3">
            <div className="grid gap-2">
                <Label htmlFor="scenario-effective_on">Vigente desde</Label>
                <DatePicker
                    id="scenario-effective_on"
                    name="effective_on"
                    value={draft.effectiveOn}
                    onChange={(effectiveOn) => onChange({ effectiveOn })}
                    invalid={errors['effective_on'] !== undefined}
                    describedBy="scenario-effective_on-error"
                />
                <InputError
                    id="scenario-effective_on-error"
                    message={errors['effective_on']}
                />
            </div>
            <NumberStepper
                id="scenario-retroactive_months"
                name="retroactive_months"
                label="Retroactivo"
                value={String(draft.months)}
                suffix={plural(draft.months, 'mes', 'meses')}
                hint={`Hasta ${limit}`}
                error={errors['retroactive_months']}
                onChange={(text) =>
                    onChange({
                        months: Math.min(
                            limit,
                            Math.max(0, Math.trunc(Number(text)) || 0),
                        ),
                    })
                }
                onStep={(direction) =>
                    onChange({
                        months: stepMonths(draft.months, direction, limit),
                    })
                }
            />
            <ChoiceField
                name="horizon_years"
                label="Años a proyectar"
                source={{ options: horizonOptions }}
                defaultValue={horizonOptions.find(
                    (option) => option.value === draft.horizon,
                )}
                error={errors['horizon_years']}
                onChange={(option) => onChange({ horizon: option.value })}
            />
        </div>
    );
}
