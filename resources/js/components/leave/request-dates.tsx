import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

type Day = {
    value: string;
    onChange: (day: string) => void;
    error: string | undefined;
};

/** The first and the last day of what the person asks for: from today on, and the last never before the first. */
export default function RequestDates({
    today,
    first,
    last,
}: {
    today: string;
    first: Day;
    last: Day;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="leave-starts-on">Primer día</Label>
                <DatePicker
                    id="leave-starts-on"
                    name="starts_on"
                    min={today}
                    value={first.value}
                    onChange={first.onChange}
                    invalid={first.error !== undefined}
                    prompt="Elija una fecha"
                />
                <InputError message={first.error} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="leave-ends-on">Último día</Label>
                <DatePicker
                    id="leave-ends-on"
                    name="ends_on"
                    min={first.value === '' ? today : first.value}
                    value={last.value}
                    onChange={last.onChange}
                    invalid={last.error !== undefined}
                    prompt="Elija una fecha"
                />
                <InputError message={last.error} />
            </div>
        </div>
    );
}
