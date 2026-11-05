import { useState } from 'react';
import ScenarioBenefitRow from '@/components/compensation/scenario-benefit-row';
import ScenarioSection from '@/components/compensation/scenario-section';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useTermChanges } from '@/hooks/use-term-changes';
import { benefitOptions } from '@/lib/compensation/benefit-options';
import type { ScenarioPreview } from '@/lib/compensation/scenario-draft';
import type { TermChangeForm } from '@/lib/compensation/scenarios';
import { toggled } from '@/lib/question-flow';
import { benefits } from '@/routes/tenant/compensation/scenarios';

/** «Cambios a prestaciones»: the benefits the scenario changes, each a row to tick with its yearly cost; another is searched on the server. */
export default function ScenarioBenefitsCard({
    initial,
    preview,
    error,
}: {
    initial: TermChangeForm[];
    preview: ScenarioPreview | null;
    error: string | undefined;
}) {
    const tenantSlug = useTenantSlug();
    const { changes, add, remove, change } = useTermChanges(initial);
    const [left, setLeft] = useState<string[]>([]);
    const [found] = useState(() => benefitOptions(benefits.url(tenantSlug)));

    return (
        <ScenarioSection
            title="Cambios a prestaciones"
            description="Lo que no toques sigue como está en el contrato."
        >
            {changes.length > 0 && (
                <ul
                    aria-label="Prestaciones que cambian"
                    className="grid gap-3"
                >
                    {changes.map((item, index) => (
                        <ScenarioBenefitRow
                            key={item.benefit_id}
                            change={item}
                            index={index}
                            included={!left.includes(item.benefit_id)}
                            yearly={
                                preview?.benefits.find(
                                    (cost) =>
                                        cost.benefit_id === item.benefit_id,
                                )?.yearly_text
                            }
                            onToggle={() =>
                                setLeft((current) =>
                                    toggled(current, item.benefit_id),
                                )
                            }
                            onValue={(value) =>
                                change(item.benefit_id, { value })
                            }
                            onRemove={() => remove(item.benefit_id)}
                        />
                    ))}
                </ul>
            )}
            <div className="grid gap-2 sm:max-w-[420px]">
                <Label htmlFor="scenario-benefit-search">
                    {changes.length === 0
                        ? 'Cambiar una prestación'
                        : 'Otra prestación'}
                </Label>
                {/* Remounted after each choice so it asks for the next one. */}
                <Combobox
                    key={changes.length}
                    id="scenario-benefit-search"
                    name=""
                    source={found.source}
                    placeholder="Busca la prestación por su nombre"
                    onChange={(option) =>
                        add({
                            value: option.value,
                            label: option.label,
                            unit: found.unitOf(option.value),
                        })
                    }
                />
            </div>
            <InputError message={error} />
        </ScenarioSection>
    );
}
