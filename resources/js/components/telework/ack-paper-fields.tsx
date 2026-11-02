import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** The day the people signed (today unless changed, never before publication) and a note about the sheet. */
export default function AckPaperFields({
    days,
    errors,
}: {
    days: { first: string; today: string };
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="ack-date">Día en que firmaron</Label>
                <DatePicker
                    id="ack-date"
                    name="date"
                    defaultValue={days.today}
                    min={days.first}
                    invalid={errors['date'] !== undefined}
                />
                <InputError role="alert" message={errors['date']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="ack-note">
                    Nota (por ejemplo, la hoja de firmas)
                </Label>
                <Textarea
                    id="ack-note"
                    name="note"
                    aria-invalid={
                        errors['note'] === undefined ? undefined : true
                    }
                />
                <InputError role="alert" message={errors['note']} />
            </div>
        </>
    );
}
