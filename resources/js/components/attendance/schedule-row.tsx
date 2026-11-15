import { useState } from 'react';
import ChooseBox from '@/components/humetria/choose-box';
import ScheduleDialog from '@/components/attendance/schedule-dialog';
import ScheduleHoursCell from '@/components/attendance/schedule-hours-cell';
import ScheduleWeekCell from '@/components/attendance/schedule-week-cell';
import Initials from '@/components/humetria/initials';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { modeLabel, shiftLabel } from '@/lib/attendance/schedule';
import type { SchedulePerson, WeeklyMaximum } from '@/types';

/**
 * A person: who, their week agreed as the ribbon with its days and rests, the hours against the year's maximum, their shift
 * and mode, or that they have no schedule (held to 8 h a day with no rest day). Whoever RH may change has a tick.
 */
export default function ScheduleRow({
    person,
    weeklyMaximum,
    canManage,
    chosen,
    onChoose,
}: {
    person: SchedulePerson;
    weeklyMaximum: WeeklyMaximum;
    canManage: boolean;
    chosen: boolean;
    onChoose: (checked: boolean) => void;
}) {
    const [editing, setEditing] = useState(false);
    const { schedule } = person;
    const none = schedule === null;

    return (
        <TableRow data-state={chosen ? 'selected' : undefined}>
            {canManage && (
                <TableCell className="w-10">
                    <ChooseBox
                        checked={chosen}
                        onChange={onChoose}
                        name={`Elegir a ${person.name}`}
                    />
                </TableCell>
            )}
            <TableCell className="max-md:flex-1 max-md:basis-40">
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{person.name}</span>
                        <span className="text-caption text-muted-foreground">
                            {person.jobTitle} · {person.center}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <ScheduleWeekCell schedule={schedule} />
            </TableCell>
            <TableCell className="min-w-36">
                <ScheduleHoursCell
                    schedule={schedule}
                    owner={person.name}
                    weeklyMaximum={weeklyMaximum}
                />
            </TableCell>
            <TableCell className="text-muted-foreground">
                {none
                    ? '—'
                    : `${shiftLabel(schedule.shiftType)} · ${modeLabel(schedule.workMode)}`}
            </TableCell>
            {canManage && (
                <TableCell className="text-right">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing(true)}
                    >
                        {none ? 'Fijar horario' : 'Cambiar'}
                    </Button>
                    {editing && (
                        <ScheduleDialog
                            open={editing}
                            onOpenChange={setEditing}
                            people={[person]}
                            weeklyMaximum={weeklyMaximum}
                        />
                    )}
                </TableCell>
            )}
        </TableRow>
    );
}
