import { Form } from '@inertiajs/react';
import { ArrowLeft, Building2, Lock } from 'lucide-react';
import EvaluationBlocked from '@/components/campaigns/evaluation-blocked';
import PopulationField from '@/components/campaigns/population-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { guidesReason } from '@/lib/evaluation-centers';
import { staffText } from '@/lib/work-centers';
import { store } from '@/routes/tenant/campaigns';
import type { WorkCenterEvaluation } from '@/types';

/**
 * Second step of a new evaluation (pantalla NOM035-Nueva): the guides its people call for and, with Guía III, the
 * sample or everyone. Going on creates the draft, where the date and the sending are the third step.
 */
export default function EvaluationGuidesStep({
    tenantSlug,
    center,
    minGroup,
    onBack,
}: {
    tenantSlug: string;
    center: WorkCenterEvaluation;
    minGroup: number;
    onBack: () => void;
}) {
    return (
        <Form {...store.form(tenantSlug)} noValidate>
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-col gap-[18px] px-6 py-[22px]">
                        <div className="flex items-center gap-3 rounded-lg bg-accent px-4 py-3.5">
                            <Building2
                                aria-hidden
                                className="size-5 shrink-0 text-primary"
                            />
                            <span className="flex grow flex-col">
                                <span className="font-medium">
                                    {center.name} · {staffText(center.workers)}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {guidesReason(center.guides)}
                                </span>
                            </span>
                            <Button
                                type="button"
                                variant="link"
                                className="h-auto px-0 font-medium"
                                onClick={onBack}
                            >
                                Cambiar
                            </Button>
                        </div>
                        <input
                            type="hidden"
                            name="work_center_id"
                            value={center.id}
                        />
                        <InputError message={errors['work_center_id']} />
                        {center.eligible ? (
                            center.guides.includes('III') && (
                                <PopulationField
                                    workers={center.workers}
                                    error={errors['population']}
                                />
                            )
                        ) : (
                            <EvaluationBlocked
                                tenantSlug={tenantSlug}
                                center={center}
                            />
                        )}
                        <p className="flex items-start gap-2.5 text-sm text-muted-foreground">
                            <Lock
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0"
                            />
                            Los resultados por área solo aparecen con {minGroup}{' '}
                            o más cuestionarios, para que nadie pueda deducir
                            quién contestó qué.
                        </p>
                    </div>
                    <div className="flex justify-between gap-2 rounded-b-xl border-t bg-muted px-6 py-4">
                        <Button type="button" variant="ghost" onClick={onBack}>
                            <ArrowLeft aria-hidden />
                            Atrás
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !center.eligible}
                        >
                            {processing && <Spinner />}
                            Siguiente: fecha y envío
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
