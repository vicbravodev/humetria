import ScenarioPeriodFields from '@/components/compensation/scenario-period-fields';
import ScenarioSection from '@/components/compensation/scenario-section';
import NumberStepper from '@/components/humetria/number-stepper';
import PillChoice from '@/components/humetria/pill-choice';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import {
    peopleHint,
    raiseSuffix,
    stepTrusted,
    trustedHint,
} from '@/lib/compensation/raise-draft';
import type { RaiseUnit } from '@/lib/compensation/scenario-draft';
import { stepRaise } from '@/lib/compensation/scenario-draft';

const UNITS: { value: RaiseUnit; label: string }[] = [
    { value: 'percent', label: 'Porcentaje' },
    { value: 'amount', label: 'Monto fijo al mes' },
];

/** «El aumento al salario» (board V4-Comp-Escenario): the raise of each group, from when, how far back and how many years. */
export default function ScenarioRaiseCard({
    draft,
    onChange,
    groups,
    horizons,
    errors,
}: {
    draft: RaiseDraft;
    onChange: (patch: Partial<RaiseDraft>) => void;
    groups: { unionized: number; trusted: number } | undefined;
    horizons: number[];
    errors: Partial<Record<string, string>>;
}) {
    return (
        <ScenarioSection title="El aumento al salario">
            <PillChoice
                name="raise_unit"
                legend="¿Cómo sube?"
                options={UNITS}
                value={draft.unit}
                onChange={(unit) => onChange({ unit })}
            />
            <div className="grid items-start gap-5 sm:grid-cols-2">
                <NumberStepper
                    id="scenario-unionized_raise"
                    name="unionized_raise"
                    label="Sindicalizados"
                    value={draft.unionized}
                    suffix={raiseSuffix(draft.unit)}
                    hint={peopleHint(groups?.unionized)}
                    error={errors['unionized_raise']}
                    onChange={(unionized) => onChange({ unionized })}
                    onStep={(direction) =>
                        onChange({
                            unionized: stepRaise(
                                draft.unionized,
                                direction,
                                draft.unit,
                            ),
                        })
                    }
                />
                <NumberStepper
                    id="scenario-trusted_raise"
                    name="trusted_raise"
                    label="Confianza"
                    value={draft.trusted}
                    suffix={raiseSuffix(draft.unit)}
                    hint={trustedHint(draft, groups?.trusted)}
                    placeholder={draft.unionized}
                    error={errors['trusted_raise']}
                    onChange={(trusted) => onChange({ trusted })}
                    onStep={(direction) =>
                        onChange({ trusted: stepTrusted(draft, direction) })
                    }
                />
            </div>
            <ScenarioPeriodFields
                draft={draft}
                onChange={onChange}
                horizons={horizons}
                errors={errors}
            />
        </ScenarioSection>
    );
}
