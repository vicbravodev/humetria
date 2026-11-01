import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

const AREA =
    'min-h-24 w-full rounded-md border border-input bg-card px-4 py-3 text-base focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive';

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
                <textarea
                    id="ack-note"
                    name="note"
                    className={AREA}
                    aria-invalid={
                        errors['note'] === undefined ? undefined : true
                    }
                />
                <InputError role="alert" message={errors['note']} />
            </div>
        </>
    );
}
