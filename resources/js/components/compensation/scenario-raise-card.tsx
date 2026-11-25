import ScenarioGroupRaises from '@/components/compensation/scenario-group-raises';
import ScenarioPayBasisField from '@/components/compensation/scenario-pay-basis-field';
import ScenarioPeriodFields from '@/components/compensation/scenario-period-fields';
import ScenarioSection from '@/components/compensation/scenario-section';
import ScenarioSingleRaise from '@/components/compensation/scenario-single-raise';
import PillChoice from '@/components/humetria/pill-choice';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import type { RaiseUnit } from '@/lib/compensation/scenario-draft';
import type { PayBasis, ScenarioSource } from '@/types';

const UNITS: { value: RaiseUnit; label: string }[] = [
    { value: 'percent', label: 'Porcentaje' },
    { value: 'amount', label: 'Monto fijo al mes' },
];

/**
 * «El aumento al salario» (board V4-Comp-Escenario): the raise of each group, from when, how far back and how many years. An
 * average payroll has one group and one raise, and is always paid monthly; the roster says how its salaries are paid.
 */
export default function ScenarioRaiseCard({
    source,
    draft,
    onChange,
    payBasis,
    onPayBasis,
    groups,
    horizons,
    errors,
}: {
    source: ScenarioSource;
    draft: RaiseDraft;
    onChange: (patch: Partial<RaiseDraft>) => void;
    payBasis: PayBasis;
    onPayBasis: (payBasis: PayBasis) => void;
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
            {source === 'average' ? (
                <ScenarioSingleRaise
                    draft={draft}
                    onChange={onChange}
                    people={
                        groups === undefined
                            ? undefined
                            : groups.unionized + groups.trusted
                    }
                    errors={errors}
                />
            ) : (
                <>
                    <ScenarioPayBasisField
                        value={payBasis}
                        onChange={onPayBasis}
                    />
                    <ScenarioGroupRaises
                        draft={draft}
                        onChange={onChange}
                        groups={groups}
                        errors={errors}
                    />
                </>
            )}
            <ScenarioPeriodFields
                draft={draft}
                onChange={onChange}
                horizons={horizons}
                errors={errors}
            />
        </ScenarioSection>
    );
}
