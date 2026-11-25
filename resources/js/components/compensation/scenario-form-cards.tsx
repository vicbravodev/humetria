import { useState } from 'react';
import ScenarioAssumptionsCard from '@/components/compensation/scenario-assumptions-card';
import ScenarioBenefitsCard from '@/components/compensation/scenario-benefits-card';
import ScenarioPayrollCard from '@/components/compensation/scenario-payroll-card';
import ScenarioRaiseCard from '@/components/compensation/scenario-raise-card';
import type {
    RaiseDraft,
    ScenarioDefaults,
} from '@/lib/compensation/raise-draft';
import { initialRaiseDraft } from '@/lib/compensation/raise-draft';
import type { ScenarioPreview } from '@/lib/compensation/scenario-draft';
import type {
    AssumptionField,
    ChoiceOption,
    PayBasis,
    ScenarioDetail,
    ScenarioPayroll,
    ScenarioSource,
} from '@/types';

/**
 * The cards of the editor of a scenario: the average payroll (only «Cotizador rápido»), the raise, the benefits it changes and the
 * assumptions. What is moved in them (the raise, how the salary is paid, the group of an average payroll) lives here; the rest of
 * the form is what they hold as fields.
 */
export default function ScenarioFormCards({
    source,
    scenario,
    assumptions,
    states,
    defaults,
    horizons,
    preview,
    errors,
}: {
    source: ScenarioSource;
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: ScenarioDefaults & ScenarioPayroll;
    horizons: number[];
    preview: ScenarioPreview | null;
    errors: Partial<Record<string, string>>;
}) {
    const [draft, setDraft] = useState<RaiseDraft>(() =>
        initialRaiseDraft(scenario, defaults),
    );
    const payroll: ScenarioPayroll = scenario ?? defaults;
    const [payBasis, setPayBasis] = useState<PayBasis>(payroll.pay_basis);
    const [group, setGroup] = useState(payroll.average_group);
    const average = source === 'average';

    return (
        <div className="grid gap-6">
            {average && (
                <ScenarioPayrollCard
                    payroll={payroll}
                    group={group}
                    onGroup={setGroup}
                    errors={errors}
                />
            )}
            <ScenarioRaiseCard
                source={source}
                payBasis={payBasis}
                onPayBasis={setPayBasis}
                draft={draft}
                onChange={(patch) =>
                    setDraft((current) => ({ ...current, ...patch }))
                }
                groups={preview?.groups}
                horizons={horizons}
                errors={errors}
            />
            <ScenarioBenefitsCard
                initial={scenario?.term_changes ?? []}
                preview={preview}
                error={errors['term_changes']}
            />
            <ScenarioAssumptionsCard
                assumptions={assumptions}
                states={states}
                {...(average ? { stateLabel: 'Estado donde trabajan' } : {})}
                defaultState={scenario?.default_state ?? ''}
                errors={errors}
            />
        </div>
    );
}
