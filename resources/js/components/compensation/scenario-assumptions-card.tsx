import { useState } from 'react';
import ChoiceField from '@/components/compensation/choice-field';
import ScenarioSection from '@/components/compensation/scenario-section';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { assumptionsSummary } from '@/lib/compensation/scenario-draft';
import type { AssumptionField, ChoiceOption } from '@/types';

/**
 * «Supuestos de los años siguientes»: a line with each value and «Revisar» to change them. Folded, the fields stay in the form,
 * so what was typed is sent; a field with a problem opens it.
 */
export default function ScenarioAssumptionsCard({
    assumptions,
    states,
    defaultState,
    errors,
}: {
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaultState: string;
    errors: Partial<Record<string, string>>;
}) {
    const [open, setOpen] = useState(false);
    const failing = Object.keys(errors).some(
        (key) => key.startsWith('assumptions') || key === 'default_state',
    );
    const shown = open || failing;
    const stateOptions = [{ value: '', label: 'Sin estado' }, ...states];

    return (
        <ScenarioSection
            title="Supuestos de los años siguientes"
            description={assumptionsSummary(assumptions)}
            action={
                <Button
                    type="button"
                    variant="outline"
                    aria-expanded={shown}
                    aria-controls="scenario-assumptions"
                    onClick={() => setOpen(!shown)}
                >
                    {shown ? 'Listo' : 'Revisar'}
                </Button>
            }
        >
            <div
                id="scenario-assumptions"
                hidden={!shown}
                className="grid gap-5"
            >
                <p className="text-sm text-muted-foreground">
                    Cada supuesto guarda su fuente y la fecha en que se fijó. Si
                    lo cambias, queda como tuyo en este escenario.
                </p>
                <div className="grid items-start gap-5 sm:grid-cols-2">
                    {assumptions.map((assumption) => (
                        <div key={assumption.key} className="grid gap-1.5">
                            <FormField
                                name={`assumptions[${assumption.key}]`}
                                idPrefix="scenario"
                                label={`${assumption.label} (% al año)`}
                                inputMode="decimal"
                                autoComplete="off"
                                defaultValue={assumption.value}
                                error={errors[`assumptions.${assumption.key}`]}
                            />
                            <p className="text-caption text-muted-foreground">
                                {assumption.source} · Fijado el{' '}
                                {describeDate(assumption.set_on)}.
                            </p>
                        </div>
                    ))}
                    <ChoiceField
                        name="default_state"
                        label="Estado de quien no tiene uno"
                        source={{ options: stateOptions }}
                        defaultValue={stateOptions.find(
                            (option) => option.value === defaultState,
                        )}
                        error={errors['default_state']}
                    />
                </div>
            </div>
        </ScenarioSection>
    );
}
