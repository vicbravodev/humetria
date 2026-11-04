import { Form, Link } from '@inertiajs/react';
import ScenarioAssumptionFields from '@/components/compensation/scenario-assumption-fields';
import ScenarioBasicsFields from '@/components/compensation/scenario-basics-fields';
import ScenarioRaiseFields from '@/components/compensation/scenario-raise-fields';
import ScenarioTermChanges from '@/components/compensation/scenario-term-changes';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index, store, update } from '@/routes/tenant/compensation/scenarios';
import type { AssumptionField, ChoiceOption, ScenarioDetail } from '@/types';

/** The scenario editor: the proposal, the raise, the benefits that change and the assumptions, saved together. */
export default function ScenarioForm({
    scenario,
    assumptions,
    states,
    defaults,
    horizons,
}: {
    scenario: ScenarioDetail | null;
    assumptions: AssumptionField[];
    states: ChoiceOption[];
    defaults: { effective_on: string; horizon_years: number };
    horizons: number[];
}) {
    const tenantSlug = useTenantSlug();
    const form =
        scenario === null
            ? store.form(tenantSlug)
            : update.form({ tenant: tenantSlug, scenario: scenario.id });

    return (
        <Form
            {...form}
            noValidate
            options={{ preserveScroll: true }}
            className="grid max-w-[820px] gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <ScenarioBasicsFields
                        scenario={scenario}
                        defaults={defaults}
                        horizons={horizons}
                        states={states}
                        errors={errors}
                    />
                    <ScenarioRaiseFields scenario={scenario} errors={errors} />
                    <ScenarioTermChanges
                        initial={scenario?.term_changes ?? []}
                        error={errors['term_changes']}
                    />
                    <ScenarioAssumptionFields
                        assumptions={assumptions}
                        errors={errors}
                    />
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar escenario
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
