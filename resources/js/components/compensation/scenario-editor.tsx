import { Form } from '@inertiajs/react';
import ScenarioCostAside from '@/components/compensation/scenario-cost-aside';
import ScenarioFormCards from '@/components/compensation/scenario-form-cards';
import ScenarioNameField from '@/components/compensation/scenario-name-field';
import { useLivePreview } from '@/hooks/use-live-preview';
import { useScenarioRoutes } from '@/hooks/use-scenario-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ScenarioDefaults } from '@/lib/compensation/raise-draft';
import type { OtherScenario } from '@/lib/compensation/scenario-draft';
import { parsePreview } from '@/lib/compensation/scenario-draft';
import type {
    AssumptionField,
    ChoiceOption,
    ScenarioDetail,
    ScenarioPayroll,
    ScenarioSource,
} from '@/types';

/**
 * The scenario with its cost as it is moved (board V4-Comp-Escenario, decision A12): every change asks the server, which costs
 * it with the engine of the report and saves nothing; «Guardar y ver reporte» saves it and opens its report.
 */
export default function ScenarioEditor({
    source,
    scenario,
    assumptions,
    states,
    defaults,
    horizons,
    others,
    vacation_notice: vacationNotice,
}: {
    source: ScenarioSource;
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: ScenarioDefaults & ScenarioPayroll;
    horizons: number[];
    others: OtherScenario[];
    vacation_notice: string | null;
}) {
    const tenantSlug = useTenantSlug();
    const { preview: previewRoute, store, update } = useScenarioRoutes();
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
                            <ScenarioFormCards
                                source={source}
                                scenario={scenario}
                                assumptions={assumptions}
                                states={states}
                                defaults={defaults}
                                horizons={horizons}
                                preview={live.result}
                                errors={shown}
                            />
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
