import { useState } from 'react';
import ScheduleDialog from '@/components/attendance/schedule-dialog';
import { Button } from '@/components/ui/button';
import { chosenText } from '@/lib/attendance/schedule';
import type { SchedulePerson, WeeklyMaximum } from '@/types';

/** Who is ticked and what can be done with them: the same schedule for all, or letting them go. */
export default function ScheduleSelectionBar({
    chosen,
    weeklyMaximum,
    onClear,
}: {
    chosen: SchedulePerson[];
    weeklyMaximum: WeeklyMaximum;
    onClear: () => void;
}) {
    const [fixing, setFixing] = useState(false);

    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-accent px-5 py-3"
        >
            <span className="font-medium">{chosenText(chosen.length)}</span>
            <div className="flex flex-wrap gap-2">
                <Button onClick={() => setFixing(true)}>
                    Fijar el mismo horario
                </Button>
                <Button variant="outline" onClick={onClear}>
                    Quitar selección
                </Button>
            </div>
            {fixing && (
                <ScheduleDialog
                    open={fixing}
                    onOpenChange={setFixing}
                    people={chosen}
                    weeklyMaximum={weeklyMaximum}
                />
            )}
        </div>
    );
}
