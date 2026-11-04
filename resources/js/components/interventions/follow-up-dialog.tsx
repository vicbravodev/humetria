import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import FormDialog from '@/components/interventions/form-dialog';
import TextField from '@/components/interventions/text-field';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { followUp } from '@/routes/tenant/campaigns/program/items';
import type { FollowUpEvaluation, ProgramRow } from '@/types';

/**
 * The evaluation after the measures (num. 8.4 e, «en su caso»): the day it is planned for (the next evaluation's, as a
 * suggestion) and, once done, what it found and the evaluation of the work center that came after, if there is one. It is a
 * note with a date: nothing is compared automatically.
 */
export default function FollowUpDialog({
    open,
    onOpenChange,
    campaignId,
    row,
    suggestion,
    evaluations,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    campaignId: string;
    row: ProgramRow;
    suggestion: string | undefined;
    evaluations: FollowUpEvaluation[];
}) {
    const tenantSlug = useTenantSlug();
    const options = evaluations.map(({ id, label }) => ({
        value: id,
        label,
    }));

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Evaluación posterior"
            description={`${row.label}. Cuándo se evalúa de nuevo y qué se encontró.`}
            form={followUp.form([tenantSlug, campaignId, row.id])}
            submit="Guardar"
        >
            {(errors) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="follow-up-due">
                                Programada para
                            </Label>
                            <DatePicker
                                id="follow-up-due"
                                name="follow_up_due_on"
                                defaultValue={row.followUp.dueOn ?? suggestion}
                            />
                            <InputError message={errors['follow_up_due_on']} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="follow-up-done">Se hizo el</Label>
                            <DatePicker
                                id="follow-up-done"
                                name="follow_up_done_on"
                                defaultValue={row.followUp.doneOn ?? undefined}
                            />
                            <InputError message={errors['follow_up_done_on']} />
                        </div>
                    </div>
                    <TextField
                        name="follow_up_result"
                        label="Qué se encontró"
                        defaultValue={row.followUp.result ?? ''}
                        error={errors['follow_up_result']}
                        multiline
                    />
                    {options.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="follow-up-evaluation">
                                Evaluación posterior del centro (opcional)
                            </Label>
                            <Combobox
                                id="follow-up-evaluation"
                                name="follow_up_campaign_id"
                                source={{ options }}
                                defaultValue={options.find(
                                    (option) =>
                                        option.value ===
                                        row.followUp.evaluation?.id,
                                )}
                                placeholder="Elige la evaluación"
                            />
                            <InputError
                                message={errors['follow_up_campaign_id']}
                            />
                        </div>
                    )}
                </>
            )}
        </FormDialog>
    );
}
