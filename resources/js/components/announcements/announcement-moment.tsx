import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import TimePicker from '@/components/humetria/time-picker';
import { Label } from '@/components/ui/label';
import type { AnnouncementRow } from '@/types';

/** One moment of an announcement, its day and its hour, as `{which}_on` and `{which}_time`. */
export default function AnnouncementMoment({
    which,
    legend,
    announcement,
    errors,
}: {
    which: 'starts' | 'ends';
    legend: string;
    announcement: AnnouncementRow | null;
    errors: Partial<Record<string, string>>;
}) {
    const dayError = errors[`${which}_on`];
    const timeError = errors[`${which}_time`];

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">{legend}</legend>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`announcement-${which}-on`}>Día</Label>
                    <DatePicker
                        id={`announcement-${which}-on`}
                        name={`${which}_on`}
                        defaultValue={announcement?.[`${which}_on`]}
                        required
                        invalid={dayError !== undefined}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`announcement-${which}-time`}>Hora</Label>
                    <TimePicker
                        id={`announcement-${which}-time`}
                        name={`${which}_time`}
                        defaultValue={announcement?.[`${which}_time`]}
                        required
                        invalid={timeError !== undefined}
                    />
                </div>
            </div>
            <InputError message={dayError ?? timeError} />
        </fieldset>
    );
}
