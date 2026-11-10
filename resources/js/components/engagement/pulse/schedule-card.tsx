import { CalendarClock } from 'lucide-react';
import { useState } from 'react';
import ScheduleDialog from '@/components/engagement/pulse/schedule-dialog';
import SchedulePauseButton from '@/components/engagement/pulse/schedule-pause-button';
import IconTile from '@/components/humetria/icon-tile';
import { Button } from '@/components/ui/button';
import { scheduleText } from '@/lib/engagement/pulse';
import type { PulseModeOption, PulseSchedule } from '@/types';

/** When the pulse goes out by itself (pantalla Eval-Activa style card): the next one, and to change or pause it. */
export default function ScheduleCard({
    schedule,
    frequencies,
    modes,
    canManage,
}: {
    schedule: PulseSchedule | null;
    frequencies: PulseModeOption[];
    modes: PulseModeOption[];
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section className="flex max-w-[640px] flex-col gap-4 card p-5 sm:p-6">
            <div className="flex items-start gap-3">
                <IconTile icon={CalendarClock} />
                <div className="flex-1">
                    <h2 className="text-lg font-semibold">
                        {schedule === null
                            ? 'Todavía no está programado'
                            : `Pulso ${schedule.frequencyLabel.toLowerCase()}`}
                    </h2>
                    <p className="text-muted-foreground">
                        {schedule === null
                            ? 'Mientras no lo programes, el pulso solo sale cuando tú lo creas y lo lanzas.'
                            : scheduleText(schedule)}
                    </p>
                </div>
            </div>
            {canManage && (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={schedule === null ? 'default' : 'outline'}
                        onClick={() => setOpen(true)}
                    >
                        {schedule === null ? 'Programar' : 'Cambiar'}
                    </Button>
                    {schedule !== null && (
                        <SchedulePauseButton schedule={schedule} />
                    )}
                </div>
            )}
            <ScheduleDialog
                open={open}
                onOpenChange={setOpen}
                schedule={schedule}
                frequencies={frequencies}
                modes={modes}
            />
        </section>
    );
}
