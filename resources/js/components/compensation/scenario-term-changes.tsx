import { useState } from 'react';
import ScenarioSection from '@/components/compensation/scenario-section';
import ScenarioTermChange from '@/components/compensation/scenario-term-change';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useTermChanges } from '@/hooks/use-term-changes';
import { benefitOptions } from '@/lib/compensation/benefit-options';
import type { TermChangeForm } from '@/lib/compensation/scenarios';
import { benefits } from '@/routes/tenant/compensation/scenarios';

/** The benefits whose terms the scenario changes, searched on the server and added one at a time. With none, the contract stays as it is. */
export default function ScenarioTermChanges({
    initial,
    error,
}: {
    initial: TermChangeForm[];
    error: string | undefined;
}) {
    const tenantSlug = useTenantSlug();
    const { changes, add, remove, change } = useTermChanges(initial);
    const [found] = useState(() => benefitOptions(benefits.url(tenantSlug)));

    return (
        <ScenarioSection
            title="Cambios a prestaciones"
            description="Una prestación nueva o con otro valor que el de hoy. Con la tabla por antigüedad, el valor cambia según los años de servicio. Lo que no agregues sigue como está en el contrato."
        >
            <div className="grid gap-2">
                <Label htmlFor="scenario-benefit-search">
                    Agregar una prestación
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
            {changes.length > 0 && (
                <ul
                    aria-label="Prestaciones que cambian"
                    className="grid gap-3"
                >
                    {changes.map((item, index) => (
                        <ScenarioTermChange
                            key={item.benefit_id}
                            change={item}
                            index={index}
                            onValue={(value) =>
                                change(item.benefit_id, { value })
                            }
                            onRemove={() => remove(item.benefit_id)}
                        />
                    ))}
                </ul>
            )}
            <InputError message={error} />
        </ScenarioSection>
    );
}
