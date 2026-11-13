import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ScenarioAssumptionsCard from '@/components/compensation/scenario-assumptions-card';
import ScenarioBenefitsCard from '@/components/compensation/scenario-benefits-card';
import ScenarioCostAside from '@/components/compensation/scenario-cost-aside';
import ScenarioNameField from '@/components/compensation/scenario-name-field';
import ScenarioRaiseCard from '@/components/compensation/scenario-raise-card';
import { useLivePreview } from '@/hooks/use-live-preview';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { RaiseDraft } from '@/lib/compensation/raise-draft';
import { initialRaiseDraft } from '@/lib/compensation/raise-draft';
import type { ScenarioDefaults } from '@/lib/compensation/raise-draft';
import type { OtherScenario } from '@/lib/compensation/scenario-draft';
import { parsePreview } from '@/lib/compensation/scenario-draft';
import {
    preview as previewRoute,
    store,
    update,
} from '@/routes/tenant/compensation/scenarios';
import type { AssumptionField, ChoiceOption, ScenarioDetail } from '@/types';

/**
 * The scenario with its cost as it is moved (board V4-Comp-Escenario, decision A12): every change asks the server, which costs
 * it with the engine of the report and saves nothing; «Guardar y ver reporte» saves it and opens its report.
 */
export default function ScenarioEditor({
    scenario,
    assumptions,
    states,
    defaults,
    horizons,
    others,
    vacation_notice: vacationNotice,
}: {
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: ScenarioDefaults;
    horizons: number[];
    others: OtherScenario[];
    vacation_notice: string | null;
}) {
    const tenantSlug = useTenantSlug();
    const [draft, setDraft] = useState<RaiseDraft>(() =>
        initialRaiseDraft(scenario, defaults),
    );
    const live = useLivePreview(previewRoute.url(tenantSlug), parsePreview);
    const form =
        scenario === null
            ? store.form(tenantSlug)
            : update.form({ tenant: tenantSlug, scenario: scenario.id });

    return (
        <Form
            {...form}
            noValidate
            options={{ preserveScroll: true }}
            onChange={live.schedule}
            onClick={live.schedule}
            onKeyUp={live.schedule}
        >
            {({ processing, errors }) => {
                const shown = { ...live.errors, ...errors };

                return (
                    <div ref={live.anchorRef} className="grid gap-7">
                        <ScenarioNameField
                            caption={
                                scenario === null
                                    ? 'Nuevo escenario'
                                    : 'Escenario'
                            }
                            defaultValue={scenario?.name ?? defaults.name}
                            error={shown['name']}
                        />
                        <div className="grid items-start gap-7 min-[1240px]:grid-cols-[minmax(0,1fr)_400px]">
                            <div className="grid gap-6">
                                <ScenarioRaiseCard
                                    draft={draft}
                                    onChange={(patch) =>
                                        setDraft((current) => ({
                                            ...current,
                                            ...patch,
                                        }))
                                    }
                                    groups={live.result?.groups}
                                    horizons={horizons}
                                    errors={shown}
                                />
                                <ScenarioBenefitsCard
                                    initial={scenario?.term_changes ?? []}
                                    preview={live.result}
                                    error={shown['term_changes']}
                                />
                                <ScenarioAssumptionsCard
                                    assumptions={assumptions}
                                    states={states}
                                    defaultState={scenario?.default_state ?? ''}
                                    errors={shown}
                                />
                            </div>
                            <ScenarioCostAside
                                preview={live.result}
                                loading={live.loading}
                                others={others}
                                vacationNotice={vacationNotice}
                                processing={processing}
                            />
                        </div>
                    </div>
                );
            }}
        </Form>
    );
}
