import { Form } from '@inertiajs/react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import AssessmentScoreField from '@/components/seating/assessment-score-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/seating/assessments';
import type { AssessmentCondition, AssessmentRecord } from '@/types';

/**
 * The seven conditions (board LS-Evaluacion: each with where it comes from and its three options as cards), the other
 * aspects and the verification date of an assessment: editable while it is a draft.
 */
export default function AssessmentForm({
    assessment,
    conditions,
    editable,
    onScore,
}: {
    assessment: AssessmentRecord;
    conditions: AssessmentCondition[];
    editable: boolean;
    /** Each point chosen, for the score beside the form. */
    onScore: (key: string, points: number) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...update.form([tenantSlug, assessment.id])}
            options={{ preserveScroll: true }}
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <div className="@container -mx-6 -mt-6 overflow-hidden rounded-t-xl border-b">
                        {conditions.map((condition) => (
                            <AssessmentScoreField
                                key={condition.key}
                                condition={condition}
                                points={assessment.scores[condition.key]}
                                personAnswered={
                                    assessment.personProvidedHealthData
                                }
                                disabled={!editable}
                                error={errors[`scores.${condition.key}`]}
                                onChoose={(points) =>
                                    onScore(condition.key, points)
                                }
                            />
                        ))}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="verified-on">
                                Fecha de verificación (obligatoria para firmar)
                            </Label>
                            <DatePicker
                                id="verified-on"
                                name="verified_on"
                                defaultValue={
                                    assessment.verifiedOn ?? undefined
                                }
                                invalid={errors['verified_on'] !== undefined}
                            />
                            <InputError message={errors['verified_on']} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="other-aspects">
                                Otros aspectos a considerar, obligatorio para
                                firmar (edad, estado de salud, embarazo…)
                            </Label>
                            <Textarea
                                id="other-aspects"
                                name="other_aspects"
                                rows={3}
                                disabled={!editable}
                                defaultValue={assessment.otherAspects ?? ''}
                            />
                            <InputError message={errors['other_aspects']} />
                            <InputError message={errors['status']} />
                        </div>
                    </div>
                    {editable && (
                        <div className="flex justify-end border-t pt-[18px]">
                            <Button variant="outline" disabled={processing}>
                                {processing && <Spinner />}
                                Guardar borrador
                            </Button>
                        </div>
                    )}
                </>
            )}
        </Form>
    );
}
