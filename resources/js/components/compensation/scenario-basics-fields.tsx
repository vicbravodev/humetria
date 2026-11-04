import { useState } from 'react';
import ChoiceField from '@/components/compensation/choice-field';
import ScenarioSection from '@/components/compensation/scenario-section';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { retroactiveLimit } from '@/lib/compensation/scenarios';
import type { ChoiceOption, ScenarioDetail } from '@/types';

type Errors = Partial<Record<string, string>>;

/** What the scenario is called, when it starts, how far back it pays and how many years it is costed over. */
export default function ScenarioBasicsFields({
    scenario,
    defaults,
    horizons,
    states,
    errors,
}: {
    scenario: ScenarioDetail | null;
    defaults: { effective_on: string; horizon_years: number };
    horizons: number[];
    states: ChoiceOption[];
    errors: Errors;
}) {
    const [horizon, setHorizon] = useState(
        String(scenario?.horizon_years ?? defaults.horizon_years),
    );
    const horizonOptions = horizons.map((years) => ({
        value: String(years),
        label: `${years} años`,
    }));
    const stateOptions = [
        {
            value: '',
            label: 'Sin estado: no se calcula el impuesto sobre nómina',
        },
        ...states,
    ];

    return (
        <ScenarioSection
            title="La propuesta"
            description="Ponle un nombre, di desde cuándo rige y hasta cuántos años quieres ver su costo."
        >
            <FormField
                name="name"
                idPrefix="scenario"
                label="Nombre"
                maxLength={120}
                defaultValue={scenario?.name ?? ''}
                error={errors['name']}
            />
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="scenario-effective_on">Vigente desde</Label>
                    <DatePicker
                        id="scenario-effective_on"
                        name="effective_on"
                        defaultValue={
                            scenario?.effective_on ?? defaults.effective_on
                        }
                        invalid={errors['effective_on'] !== undefined}
                        describedBy="scenario-effective_on-error"
                    />
                    <InputError
                        id="scenario-effective_on-error"
                        message={errors['effective_on']}
                    />
                </div>
                <ChoiceField
                    name="horizon_years"
                    label="Años a proyectar"
                    source={{ options: horizonOptions }}
                    defaultValue={horizonOptions.find(
                        (option) => option.value === horizon,
                    )}
                    error={errors['horizon_years']}
                    onChange={(option) => setHorizon(option.value)}
                />
                <FormField
                    name="retroactive_months"
                    idPrefix="scenario"
                    label={`Meses de retroactivo (hasta ${retroactiveLimit(horizon)})`}
                    inputMode="numeric"
                    autoComplete="off"
                    defaultValue={String(scenario?.retroactive_months ?? 0)}
                    error={errors['retroactive_months']}
                />
                <ChoiceField
                    name="default_state"
                    label="Estado de quien no tiene uno"
                    source={{ options: stateOptions }}
                    defaultValue={stateOptions.find(
                        (option) =>
                            option.value === (scenario?.default_state ?? ''),
                    )}
                    error={errors['default_state']}
                />
            </div>
        </ScenarioSection>
    );
}
