import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
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
                <div className="grid gap-2">
                    <Label htmlFor={`scheduled-at-${request.id}`}>
                        Día y hora
                    </Label>
                    <Input
                        id={`scheduled-at-${request.id}`}
                        name="scheduled_at"
                        type="datetime-local"
                        required
                    />
                    <InputError message={errors['scheduled_at']} />
                </div>
            )}
        </FormDialog>
    );
}
