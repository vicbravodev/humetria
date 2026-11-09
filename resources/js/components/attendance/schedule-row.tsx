import { useState } from 'react';
import ScheduleDialog from '@/components/attendance/schedule-dialog';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { duration } from '@/lib/attendance/week-text';
import {
    modeLabel,
    scheduleDays,
    shiftLabel,
    weekTotal,
} from '@/lib/attendance/schedule';
import type { SchedulePerson, WeeklyMaximum } from '@/types';

/** A person: who, where, their week agreed («L–V 8 h · 40 h»), their shift and mode, or that they have no schedule. */
export default function ScheduleRow({
    person,
    weeklyMaximum,
    canManage,
}: {
    person: SchedulePerson;
    weeklyMaximum: WeeklyMaximum;
    canManage: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const { schedule } = person;

    return (
        <TableRow>
            <TableCell>
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{person.name}</span>
                        <span className="text-caption text-muted-foreground">
                            {person.jobTitle}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-muted-foreground">
                {person.center}
            </TableCell>
            <TableCell>
                {schedule === null ? (
                    <Badge variant="warning">Sin horario</Badge>
                ) : (
                    <div className="flex flex-col">
                        <span className="tabular-nums">
                            {scheduleDays(schedule.minutes)}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {duration(weekTotal(schedule.minutes))} a la semana
                        </span>
                    </div>
                )}
            </TableCell>
            <TableCell className="text-muted-foreground">
                {schedule === null
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
                        {schedule === null ? 'Fijar horario' : 'Cambiar'}
                    </Button>
                    {editing && (
                        <ScheduleDialog
                            open={editing}
                            onOpenChange={setEditing}
                            person={person}
                            weeklyMaximum={weeklyMaximum}
                        />
                    )}
                </TableCell>
            )}
        </TableRow>
    );
}
