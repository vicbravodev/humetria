import ChoiceField from '@/components/humetria/choice-field';
import SaveError from '@/components/humetria/save-error';
import AppliesResult from '@/components/tenancy/applies-result';
import AppliesSeatingAction from '@/components/tenancy/applies-seating-action';
import { useSeatingApplies } from '@/hooks/use-seating-applies';
import type { SeatingDeclaration } from '@/hooks/use-seating-applies';
import { SECTORS, seatingResult } from '@/lib/applicability';
import { certificate } from '@/routes/tenant/seating';
import type { NavItem } from '@/types';

const STANDING: { value: 'yes' | 'no'; label: string }[] = [
    { value: 'yes', label: 'Sí' },
    { value: 'no', label: 'No, nadie' },
];

/**
 * Ley Silla in «¿Qué te pide cada norma en este centro?» (board V2-Aplica-Otras): whether someone works standing and, if
 * so, what the center does; the result and its action at the side.
 */
export default function AppliesSeating({
    tenantSlug,
    workCenterId,
    saved,
    routeHref,
    panelHref,
}: {
    tenantSlug: string;
    workCenterId: string;
    saved: SeatingDeclaration | null;
    routeHref: NavItem['href'];
    panelHref: NavItem['href'];
}) {
    const form = useSeatingApplies(tenantSlug, workCenterId, saved);
    const result = seatingResult(form.standing, form.sector);

    return (
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-12">
            <div className="flex flex-col gap-6">
                <ChoiceField
                    name="standing"
                    legend="¿Alguien hace su trabajo de pie, aunque sea parte de su jornada?"
                    options={STANDING}
                    value={
                        form.standing === null
                            ? null
                            : form.standing
                              ? 'yes'
                              : 'no'
                    }
                    onChange={(value) => form.answerStanding(value === 'yes')}
                    columns={2}
                    size="lg"
                />
                {form.standing === true && (
                    <ChoiceField
                        name="sector"
                        legend="¿A qué se dedica el centro?"
                        options={SECTORS}
                        value={form.sector}
                        onChange={form.answerSector}
                        columns={2}
                        size="lg"
                        error={form.saving.errors['centers.0.sector']}
                    />
                )}
                {form.saving.failed && (
                    <SaveError
                        onRetry={form.saving.retry}
                        processing={form.saving.processing}
                    />
                )}
            </div>
            <AppliesResult
                result={result}
                action={
                    <AppliesSeatingAction
                        applies={result.applies}
                        changed={form.changed}
                        processing={form.saving.processing}
                        declaredOn={saved?.declaredOn ?? null}
                        onSave={form.save}
                        routeHref={routeHref}
                        panelHref={panelHref}
                        certificateHref={certificate([
                            tenantSlug,
                            workCenterId,
                        ])}
                    />
                }
            />
        </div>
    );
}
