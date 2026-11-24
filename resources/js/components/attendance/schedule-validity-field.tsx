import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

/**
 * The day the schedule starts: today unless RH picks another. A schedule never erases the one before; each day is counted
 * with the one in effect that day. A day in the past corrects the record and stays in the audit trail with what it changed.
 */
export default function ScheduleValidityField({
    today,
    error,
}: {
    today: string;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="schedule-valid-from">Empieza el</Label>
            <DatePicker
                id="schedule-valid-from"
                name="valid_from"
                defaultValue={today}
                required
                invalid={error !== undefined}
                describedBy="schedule-valid-from-hint"
            />
            <InputError message={error} />
            <p
                id="schedule-valid-from-hint"
                className="text-sm text-pretty text-muted-foreground"
            >
                Cada día se cuenta con el horario vigente ese día; el anterior
                no se borra. Si eliges una fecha pasada, corriges el registro y
                queda en la bitácora.
            </p>
        </div>
    );
}
