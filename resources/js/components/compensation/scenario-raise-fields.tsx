import ScenarioSection from '@/components/compensation/scenario-section';
import FormField from '@/components/form-field';
import type { ScenarioDetail } from '@/types';

type Errors = Partial<Record<string, string>>;

/** The raise to the daily salary of each staff group, as a percent. The trusted staff copy the unionized raise unless they have their own. */
export default function ScenarioRaiseFields({
    scenario,
    errors,
}: {
    scenario: ScenarioDetail | null;
    errors: Errors;
}) {
    return (
        <ScenarioSection
            title="Aumento al salario"
            description="Un porcentaje por grupo. También se aplica al sueldo individual de quien lo tiene. Si dejas vacío el de confianza, copia el de sindicalizados."
        >
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="unionized_raise"
                    idPrefix="scenario"
                    label="Sindicalizados (%)"
                    inputMode="decimal"
                    autoComplete="off"
                    defaultValue={scenario?.unionized_raise ?? '0'}
                    error={errors['unionized_raise']}
                />
                <FormField
                    name="trusted_raise"
                    idPrefix="scenario"
                    label="Confianza (%)"
                    placeholder="Igual que sindicalizados"
                    inputMode="decimal"
                    autoComplete="off"
                    defaultValue={scenario?.trusted_raise ?? ''}
                    error={errors['trusted_raise']}
                />
            </div>
        </ScenarioSection>
    );
}
