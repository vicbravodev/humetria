import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import TimePicker from '@/components/humetria/time-picker';
import { Label } from '@/components/ui/label';
import { schedule } from '@/routes/demo-requests';
import type { DemoRequestRow } from '@/types';

/** The day and hour of the demo, in Mexico City time; the request moves to «Demo agendada». */
export default function ScheduleDemoDialog({
    request,
    open,
    onOpenChange,
}: {
    request: DemoRequestRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Agendar demo"
            description={`La demo con ${request.company}. La hora es la de la Ciudad de México.`}
            form={schedule.form(request.id)}
            submit="Agendar"
        >
            {(errors) => (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`scheduled-on-${request.id}`}>
                            Día
                        </Label>
                        <DatePicker
                            id={`scheduled-on-${request.id}`}
                            name="scheduled_on"
                            required
                            invalid={errors['scheduled_at'] !== undefined}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`scheduled-time-${request.id}`}>
                            Hora
                        </Label>
                        <TimePicker
                            id={`scheduled-time-${request.id}`}
                            name="scheduled_time"
                            required
                            invalid={errors['scheduled_at'] !== undefined}
                        />
                    </div>
                    <InputError
                        className="sm:col-span-2"
                        message={errors['scheduled_at']}
                    />
                </div>
            )}
        </FormDialog>
    );
}
