import ScenarioSection from '@/components/compensation/scenario-section';
import FormField from '@/components/form-field';
import { describeDate } from '@/lib/calendar-date';
import type { AssumptionField } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The assumptions of the long-term projection, each with its value as a yearly percent and where it comes from. */
export default function ScenarioAssumptionFields({
    assumptions,
    errors,
}: {
    assumptions: AssumptionField[];
    errors: Errors;
}) {
    return (
        <ScenarioSection
            title="Supuestos de largo plazo"
            description="Valen desde el segundo año. Cada uno guarda su fuente y la fecha en que se fijó; si lo cambias, queda como tuyo en este escenario."
        >
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
            </div>
        </ScenarioSection>
    );
}
