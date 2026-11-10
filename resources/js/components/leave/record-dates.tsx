import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

/** The first and the last day RH records: any day, past ones included, and the last never before the first. */
export default function RecordDates({
    startsOn,
    onStartsOn,
    errors,
}: {
    startsOn: string;
    onStartsOn: (day: string) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="record-starts-on">Primer día</Label>
                <DatePicker
                    id="record-starts-on"
                    name="starts_on"
                    value={startsOn}
                    onChange={onStartsOn}
                    invalid={errors['starts_on'] !== undefined}
                />
                <InputError message={errors['starts_on']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="record-ends-on">Último día</Label>
                <DatePicker
                    id="record-ends-on"
                    name="ends_on"
                    min={startsOn === '' ? undefined : startsOn}
                    invalid={errors['ends_on'] !== undefined}
                />
                <InputError message={errors['ends_on']} />
            </div>
        </div>
    );
}
