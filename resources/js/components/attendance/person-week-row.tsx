import { Link } from '@inertiajs/react';
import PersonWeekNotes from '@/components/attendance/person-week-notes';
import Initials from '@/components/humetria/initials';
import WeekRibbon from '@/components/humetria/week-ribbon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    hoursSplit,
    overviewRibbon,
    weekBadge,
} from '@/lib/attendance/overview';
import { index as schedules } from '@/routes/tenant/attendance/schedules';
import type { OverviewPerson } from '@/types';

/**
 * One person's week (board A1): who and where, the ribbon of their days with the hours and the overtime, what warns RH
 * (the limits broken, the week near the cap, no agreed schedule), the days away with vacations or a leave, their answer and the next step: fixing the schedule
 * when they have none, since without it they are held to 8 h a day with no rest day.
 */
export default function PersonWeekRow({
    person,
    over,
}: {
    person: OverviewPerson;
    over: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const badge = weekBadge(person.status, over);
    const hours = hoursSplit(person.ordinaryMinutes, person.overtimeMinutes);
    const toSchedule = schedules(tenantSlug, {
        query: { status: 'unscheduled', q: person.name },
    });

    return (
        <TableRow>
            <TableCell className="max-md:w-full">
                <div className="flex items-center gap-3">
                    <Initials name={person.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{person.name}</span>
                        <span className="text-caption text-muted-foreground">
                            {`${person.jobTitle} · ${person.center}`}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <div className="flex items-center gap-3">
                    <WeekRibbon days={overviewRibbon(person)} />
                    <div className="flex flex-col whitespace-nowrap tabular-nums">
                        <span
                            className={
                                person.ordinaryMinutes +
                                    person.overtimeMinutes ===
                                0
                                    ? 'text-muted-foreground'
                                    : 'font-semibold'
                            }
                        >
                            {hours.total}
                        </span>
                        <span className="text-caption text-muted-foreground">
                            {hours.extra ?? ' '}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell className="min-w-56 whitespace-normal">
                <PersonWeekNotes
                    person={person}
                    scheduleHref={toSchedule.url}
                />
            </TableCell>
            <TableCell>
                <Badge variant={badge.variant}>{badge.label}</Badge>
            </TableCell>
            <TableCell className="text-right">
                {!person.hasSchedule && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={toSchedule}>Fijar horario</Link>
                    </Button>
                )}
            </TableCell>
        </TableRow>
    );
}
