import { Form } from '@inertiajs/react';
import { Pause, Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/pulse/schedule';
import type { PulseSchedule } from '@/types';

/** Pauses the schedule or takes it up again with everything else as it is: the row stays either way. */
export default function SchedulePauseButton({
    schedule,
}: {
    schedule: PulseSchedule;
}) {
    const tenantSlug = useTenantSlug();
    const Icon = schedule.active ? Pause : Play;

    return (
        <Form
            {...update.form.put(tenantSlug)}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <>
                    <input
                        type="hidden"
                        name="frequency"
                        value={schedule.frequency}
                    />
                    <input
                        type="hidden"
                        name="day_of_month"
                        value={schedule.dayOfMonth}
                    />
                    <input
                        type="hidden"
                        name="open_days"
                        value={schedule.openDays}
                    />
                    <input
                        type="hidden"
                        name="next_mode"
                        value={schedule.nextMode ?? ''}
                    />
                    <input
                        type="hidden"
                        name="active"
                        value={schedule.active ? '0' : '1'}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing ? <Spinner /> : <Icon aria-hidden="true" />}
                        {schedule.active ? 'Pausar' : 'Reanudar'}
                    </Button>
                </>
            )}
        </Form>
    );
}
