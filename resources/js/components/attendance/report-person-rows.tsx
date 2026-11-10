import ReportDayRow from '@/components/attendance/report-day-row';
import ReportPersonNotices from '@/components/attendance/report-person-notices';
import Initials from '@/components/humetria/initials';
import WeekRibbon from '@/components/humetria/week-ribbon';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { confirmationBadge } from '@/lib/attendance/report';
import {
    answerOf,
    hasDayInProgress,
    personHours,
    personNotices,
    personRibbon,
} from '@/lib/attendance/report-person';
import type { ReportPerson } from '@/types';

/**
 * A person of the report (board A7): who, the ribbon of their week with their hours, what an inspector should notice and
 * the answer to their week; then each of their days in the period.
 */
export default function ReportPersonRows({ person }: { person: ReportPerson }) {
    const ribbon = personRibbon(person.days);
    const hours = personHours(person.days);
    const answer = confirmationBadge(answerOf(person.days));

    return (
        <>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
                <TableCell colSpan={2} className="max-md:w-full">
                    <div className="flex items-center gap-3">
                        <Initials name={person.name} />
                        <div className="flex min-w-0 flex-col">
                            <span className="font-medium">{person.name}</span>
                            <span className="text-caption text-muted-foreground">
                                {person.jobTitle} · {person.days.length}{' '}
                                {person.days.length === 1 ? 'día' : 'días'}
                            </span>
                        </div>
                    </div>
                </TableCell>
                <TableCell colSpan={2}>
                    <div className="flex items-center gap-3">
                        {ribbon !== null && <WeekRibbon days={ribbon} />}
                        <div className="flex flex-col whitespace-nowrap tabular-nums">
                            <span className="font-semibold">{hours.total}</span>
                            <span className="text-caption text-muted-foreground">
                                {hours.extra ?? ' '}
                            </span>
                        </div>
                    </div>
                </TableCell>
                <TableCell
                    colSpan={2}
                    className="whitespace-normal max-md:w-full"
                >
                    <ReportPersonNotices
                        notices={personNotices(person.days)}
                        inProgress={hasDayInProgress(person.days)}
                    />
                </TableCell>
                <TableCell>
                    <Badge variant={answer.variant}>{answer.label}</Badge>
                </TableCell>
            </TableRow>
            {person.days.map((day) => (
                <ReportDayRow key={day.date} day={day} />
            ))}
        </>
    );
}
