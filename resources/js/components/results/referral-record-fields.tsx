import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ScreeningReferral } from '@/types';

/**
 * «3. Registro» (board V4-N035-Canalizacion): the day, a note without clinical detail, the reminder in 30 days and, on a
 * record already kept, that the person was attended.
 */
export default function ReferralRecordFields({
    referral,
    today,
    errors,
}: {
    referral: ScreeningReferral | null;
    today: string;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <fieldset className="grid gap-4">
            <legend className="mb-3 text-lg font-semibold">3. Registro</legend>
            <div className="grid gap-4 @lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <div className="grid content-start gap-2">
                    <Label htmlFor="referred-on">Fecha</Label>
                    <DatePicker
                        id="referred-on"
                        name="referred_on"
                        defaultValue={referral?.referredOn ?? today}
                        max={today}
                        invalid={errors['referred_on'] !== undefined}
                        describedBy="referred-on-error"
                    />
                    <InputError
                        id="referred-on-error"
                        message={errors['referred_on']}
                    />
                </div>
                <div className="grid content-start gap-2">
                    <Label htmlFor="referral-note">
                        Nota{' '}
                        <span className="font-normal text-muted-foreground">
                            opcional, sin detalle clínico
                        </span>
                    </Label>
                    <Input
                        id="referral-note"
                        name="note"
                        maxLength={200}
                        defaultValue={referral?.note ?? ''}
                        placeholder="Por ejemplo: cita en la UMF 12 el lunes"
                        aria-invalid={errors['note'] !== undefined}
                        aria-describedby="referral-note-error"
                    />
                    <InputError
                        id="referral-note-error"
                        message={errors['note']}
                    />
                </div>
            </div>
            <label className="flex min-h-11 cursor-pointer items-center gap-3">
                <input
                    type="checkbox"
                    name="remind"
                    value="1"
                    defaultChecked={
                        referral === null || referral.remindOn !== null
                    }
                    className="size-[18px] accent-primary"
                />
                Recordarme en 30 días preguntar cómo sigue
            </label>
            {referral !== null && (
                <label className="flex min-h-11 cursor-pointer items-center gap-3">
                    <input
                        type="checkbox"
                        name="attended"
                        value="1"
                        defaultChecked={referral.status === 'attended'}
                        className="size-[18px] accent-primary"
                    />
                    Ya la atendieron
                </label>
            )}
        </fieldset>
    );
}
