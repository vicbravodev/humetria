import { TriangleAlert } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { hoursText, weekBadge, weekNotes } from '@/lib/attendance/overview';
import type { OverviewPerson } from '@/types';

/** One person's week: who, where, the hours split as the law pays them, the limits broken and their answer. */
export default function PersonWeekRow({
    person,
    over,
}: {
    person: OverviewPerson;
    over: boolean;
}) {
    const badge = weekBadge(person.status, over);
    const notes = weekNotes(person);

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
            <TableCell className="tabular-nums">
                {hoursText(person.ordinaryMinutes, person.overtimeMinutes)}
            </TableCell>
            <TableCell className="min-w-56 whitespace-normal">
                {notes.length === 0 ? (
                    <span className="text-muted-foreground">Sin avisos</span>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {notes.map((note) => (
                            <li
                                key={note}
                                className="flex items-start gap-1.5 text-sm text-destructive"
                            >
                                <TriangleAlert
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                {note}
                            </li>
                        ))}
                    </ul>
                )}
            </TableCell>
            <TableCell>
                <Badge variant={badge.variant}>{badge.label}</Badge>
            </TableCell>
        </TableRow>
    );
}
