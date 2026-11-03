import { Form } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import Notice from '@/components/humetria/notice';
import SettingRow from '@/components/humetria/setting-row';
import AssessmentScoreField from '@/components/seating/assessment-score-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/seating/assessments';
import type { AssessmentCondition, AssessmentRecord } from '@/types';

/** The seven conditions, the other aspects and the verification date of an assessment: editable while it is a draft. */
export default function AssessmentForm({
    assessment,
    conditions,
    editable,
}: {
    assessment: AssessmentRecord;
    conditions: AssessmentCondition[];
    editable: boolean;
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
                    <SettingRow
                        icon={SlidersHorizontal}
                        title="Las siete condicionantes"
                        description="Confirma o corrige cada una. El total y el nivel los calcula la plataforma al firmar."
                    >
                        {!assessment.personProvidedHealthData && (
                            <Notice>
                                <strong>
                                    La persona no contestó sobre sus molestias.
                                </strong>{' '}
                                La condicionante f vale 1 hasta que la Comisión
                                la corrija, y así queda registrado.
                            </Notice>
                        )}
                        <div className="grid gap-6">
                            {conditions.map((condition) => (
                                <AssessmentScoreField
                                    key={condition.key}
                                    condition={condition}
                                    points={assessment.scores[condition.key]}
                                    disabled={!editable}
                                    error={errors[`scores.${condition.key}`]}
                                />
                            ))}
                        </div>
                    </SettingRow>
                    <div className="grid gap-4 border-t pt-7 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="verified-on">
                                Fecha de verificación
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
                                Otros aspectos a considerar (edad, estado de
                                salud, embarazo…)
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
