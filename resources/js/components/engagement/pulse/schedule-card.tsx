import { CalendarClock } from 'lucide-react';
import { useState } from 'react';
import ScheduleDialog from '@/components/engagement/pulse/schedule-dialog';
import SchedulePauseButton from '@/components/engagement/pulse/schedule-pause-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    cadenceText,
    leavesIn,
    modeHint,
    nextRunTitle,
    upcomingText,
} from '@/lib/engagement/pulse';
import type { PulseModeOption, PulseSchedule } from '@/types';

/**
 * «Siguiente pulso» (pantalla Pulso · Inicio): the day it goes out, how often, what it will look at and, for who manages
 * it, to change or pause it. Without a schedule, the way to set one.
 */
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
        <section
            aria-labelledby="next-pulse"
            className="flex min-w-0 flex-col gap-5 card p-5 sm:p-6"
        >
            <div className="flex items-center justify-between gap-3">
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <CalendarClock aria-hidden className="size-4" />
                    Siguiente pulso
                </p>
                {schedule?.active === true && (
                    <Badge variant="warning">
                        {leavesIn(schedule.daysUntil)}
                    </Badge>
                )}
            </div>
            <div className="flex flex-col gap-1">
                <h2
                    id="next-pulse"
                    className="text-2xl leading-8 font-semibold tracking-[-0.01em]"
                >
                    {schedule === null
                        ? 'Todavía no está programado'
                        : nextRunTitle(schedule)}
                </h2>
                <p className="text-muted-foreground">
                    {schedule === null
                        ? 'Mientras no lo programes, el pulso solo sale cuando tú lo creas y lo lanzas.'
                        : cadenceText(schedule)}
                </p>
            </div>
            <div className="flex flex-wrap items-end justify-between gap-4 border-t pt-5">
                {schedule !== null && (
                    <div className="flex max-w-[460px] flex-col gap-1">
                        <p className="text-lg font-medium">
                            {upcomingText(schedule)}
                        </p>
                        <p className="text-muted-foreground">
                            {modeHint(schedule.upcoming.mode)}
                        </p>
                    </div>
                )}
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
            </div>
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
