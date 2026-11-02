import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { END_REASONS } from '@/lib/telework/roster';
import type { TeleworkerSummary } from '@/types';

const CARD =
    'flex cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

/** Why, from which day and the note of ending a period. */
export default function EndTeleworkFields({
    teleworker,
    errors,
}: {
    teleworker: TeleworkerSummary;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <fieldset className="grid gap-2">
                <legend className="mb-1 font-medium">Motivo</legend>
                {END_REASONS.map((reason) => (
                    <label key={reason.value} className={CARD}>
                        <input
                            type="radio"
                            name="reason"
                            value={reason.value}
                            className="size-5 shrink-0 accent-primary"
                        />
                        {reason.label}
                    </label>
                ))}
                <InputError message={errors['reason']} />
            </fieldset>
            <div className="grid gap-2">
                <Label htmlFor="end-date">Fecha</Label>
                <DatePicker
                    id="end-date"
                    name="ended_on"
                    defaultValue={teleworker.today}
                    min={teleworker.startedOn ?? undefined}
                    invalid={errors['ended_on'] !== undefined}
                    describedBy="end-date-error"
                />
                <InputError id="end-date-error" message={errors['ended_on']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="end-note">Nota (opcional)</Label>
                <Textarea
                    id="end-note"
                    name="note"
                    aria-invalid={
                        errors['note'] === undefined ? undefined : true
                    }
                />
                <InputError message={errors['note']} />
            </div>
        </>
    );
}
