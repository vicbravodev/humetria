import ReportCorrectSheet from '@/components/attendance/report-correct-sheet';
import ReportDayNotes from '@/components/attendance/report-day-notes';
import ReportEvidence from '@/components/attendance/report-evidence';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { confirmationBadge, dayHours, endText } from '@/lib/attendance/report';
import { lateLabel } from '@/lib/attendance/late';
import { absenceLabel, dayTitle, duration } from '@/lib/attendance/week-text';
import type { ReportDay } from '@/types';

/** One day of a person: its local start and end, breaks, hours as the law pays them, evidence, corrections and answer. */
export default function ReportDayRow({ day }: { day: ReportDay }) {
    const badge = confirmationBadge(day.confirmation);
    const late = lateLabel(day.lateMinutes);
    const away = absenceLabel(day.absence ?? null);

    return (
        <TableRow>
            <TableCell className="pl-8 font-medium whitespace-nowrap max-md:w-full">
                {dayTitle(day.date)}
            </TableCell>
            <TableCell className="whitespace-nowrap tabular-nums">
                {day.start ?? '—'} – {endText(day)}
            </TableCell>
            <TableCell className="tabular-nums">
                <span className="text-muted-foreground md:hidden">Pausas </span>
                {day.breakMinutes === 0 ? '—' : duration(day.breakMinutes)}
            </TableCell>
            <TableCell className="tabular-nums">{dayHours(day)}</TableCell>
            <TableCell className="whitespace-nowrap">
                <ReportEvidence level={day.evidence} />
            </TableCell>
            <TableCell className="min-w-56 whitespace-normal max-md:w-full">
                {away !== null && (
                    <Badge variant="secondary" className="mr-1 mb-1">
                        {away}
                    </Badge>
                )}
                {late !== null && (
                    <Badge variant="warning" className="mb-1">
                        {late}
                    </Badge>
                )}
                <ReportDayNotes day={day} />
            </TableCell>
            <TableCell>
                <div className="flex flex-col items-start gap-2">
                    <Badge variant={badge.variant}>{badge.label}</Badge>
                    {(day.marks ?? []).length > 0 && (
                        <ReportCorrectSheet
                            date={day.date}
                            marks={day.marks ?? []}
                        />
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}
