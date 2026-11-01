import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import type { NoticeKindValue } from '@/types';

/** When the notice was written and when it happened (the latter may be earlier). */
export default function PortalNoticeDates({
    kind,
    today,
    errors,
}: {
    kind: NoticeKindValue;
    today: string;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${kind}-written_on`}>
                    Fecha de elaboración
                </Label>
                <DatePicker
                    id={`${kind}-written_on`}
                    name="written_on"
                    defaultValue={today}
                    invalid={errors['written_on'] !== undefined}
                    describedBy={`${kind}-written_on-error`}
                />
                <InputError
                    id={`${kind}-written_on-error`}
                    role="alert"
                    message={errors['written_on']}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${kind}-occurred_on`}>
                    Fecha en que ocurrió
                </Label>
                <DatePicker
                    id={`${kind}-occurred_on`}
                    name="occurred_on"
                    defaultValue={today}
                    invalid={errors['occurred_on'] !== undefined}
                    describedBy={`${kind}-occurred_on-error`}
                />
                <InputError
                    id={`${kind}-occurred_on-error`}
                    role="alert"
                    message={errors['occurred_on']}
                />
            </div>
        </div>
    );
}
